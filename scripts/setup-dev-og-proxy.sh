#!/usr/bin/env bash
set -euo pipefail

# This script configures Apache on a Bitnami Lightsail host to proxy
# crawler requests for /<username> and OG assets (/users/*, /docs/*)
# to the Laravel API, so sharing https://<host>/<username> renders
# the correct OpenGraph preview (bookshelf image).

# Requirements:
# - SSH host alias "livrolog" configured in ~/.ssh/config
# - API origin domain (defaults to https://api.dev.livrolog.com)

API_ORIGIN=${API_ORIGIN:-"https://api.dev.livrolog.com"}
REMOTE_SNIPPET="/opt/bitnami/apache2/conf/bitnami/extra/livrolog-og-proxy.conf"
REMOTE_VHOST="/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf"

read -r -d '' SNIPPET <<'EOF'
# LivroLog OG Proxy snippet
ProxyPreserveHost On
ProxyRequests Off
SSLProxyEngine On

# Always proxy API image endpoints and docs to the API
ProxyPass        /users/ __API_ORIGIN__/users/
ProxyPassReverse /users/ __API_ORIGIN__/users/
ProxyPass        /docs/  __API_ORIGIN__/docs/
ProxyPassReverse /docs/  __API_ORIGIN__/docs/

RewriteEngine On
# /username (single segment), bots or ?og=1 → proxy to API
RewriteCond %{QUERY_STRING} (^|&)og=1(&|$) [OR]
RewriteCond %{HTTP_USER_AGENT} "(facebookexternalhit|facebookcatalog|twitterbot|linkedinbot|whatsapp|telegram(bot)?|slack(-imgproxy|-bot|bot)?|discordbot|skypeuripreview|applebot|google(bot|-inspectiontool)|bingbot|yahoo!|pinterest(bot)?|reddit(bot)?|embedly|vkshare|qwantify|bitlybot|bufferbot|iframely|opengraph|lighthouse|pagespeed|duckduckbot|baiduspider|yandex(bot)?)" [NC]
RewriteCond %{REQUEST_URI} ^/([A-Za-z0-9_.-]+)/?$ 
RewriteCond %1 !^(api|login|register|reset-password|documentation|privacy|terms|sitemap\.xml|robots\.txt|assets|favicon\.ico)$ [NC]
RewriteRule ^ __API_ORIGIN__%{REQUEST_URI}?%{QUERY_STRING} [P,L]

# Homepage for bots or ?og=1 → proxy to API
RewriteCond %{QUERY_STRING} (^|&)og=1(&|$) [OR]
RewriteCond %{HTTP_USER_AGENT} "(facebookexternalhit|facebookcatalog|twitterbot|linkedinbot|whatsapp|telegram(bot)?|slack(-imgproxy|-bot|bot)?|discordbot|skypeuripreview|applebot|google(bot|-inspectiontool)|bingbot|yahoo!|pinterest(bot)?|reddit(bot)?|embedly|vkshare|qwantify|bitlybot|bufferbot|iframely|opengraph|lighthouse|pagespeed|duckduckbot|baiduspider|yandex(bot)?)" [NC]
RewriteCond %{REQUEST_URI} ^/$
RewriteRule ^ __API_ORIGIN__/ [P,L]

# SPA fallback (safe if already present)
FallbackResource /index.html
EOF

SNIPPET=${SNIPPET//__API_ORIGIN__/${API_ORIGIN}}

echo "Configuring OG proxy on remote host 'livrolog' (API_ORIGIN=${API_ORIGIN})"

# Create a local temp file with the snippet and copy to remote
TMP_SNIP="$(mktemp)"
printf "%s\n" "$SNIPPET" > "$TMP_SNIP"
scp "$TMP_SNIP" livrolog:/tmp/livrolog-og-proxy.conf >/dev/null
rm -f "$TMP_SNIP"

ssh livrolog "sudo bash -s" <<'EOSSH'
set -euo pipefail
SNIPPET_PATH="/opt/bitnami/apache2/conf/bitnami/extra/livrolog-og-proxy.conf"
VHOST="/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf"

sudo mkdir -p "$(dirname "$SNIPPET_PATH")"
sudo mv /tmp/livrolog-og-proxy.conf "$SNIPPET_PATH"
sudo chown root:root "$SNIPPET_PATH"
sudo chmod 0644 "$SNIPPET_PATH"

# Backup vhost
sudo cp -n "$VHOST" "$VHOST.$(date +%Y%m%d%H%M%S).bak"

# Ensure required modules are loaded (Bitnami usually has them, but be safe)
if ! grep -q '^LoadModule proxy_module' /opt/bitnami/apache2/conf/httpd.conf; then
  echo 'LoadModule proxy_module modules/mod_proxy.so' | sudo tee -a /opt/bitnami/apache2/conf/httpd.conf >/dev/null
fi
if ! grep -q '^LoadModule proxy_http_module' /opt/bitnami/apache2/conf/httpd.conf; then
  echo 'LoadModule proxy_http_module modules/mod_proxy_http.so' | sudo tee -a /opt/bitnami/apache2/conf/httpd.conf >/dev/null
fi
if ! grep -q '^LoadModule rewrite_module' /opt/bitnami/apache2/conf/httpd.conf; then
  echo 'LoadModule rewrite_module modules/mod_rewrite.so' | sudo tee -a /opt/bitnami/apache2/conf/httpd.conf >/dev/null
fi

# Include the snippet before the closing VirtualHost of :443
if ! grep -q "Include .*livrolog-og-proxy.conf" "$VHOST"; then
  sudo sed -i '/<\/VirtualHost>/i \\    Include \/opt\/bitnami\/apache2\/conf\/bitnami\/extra\/livrolog-og-proxy.conf' "$VHOST"
fi

sudo apachectl -t
sudo /opt/bitnami/ctlscript.sh restart apache
EOSSH

echo "Done. Validate with:"
echo "  curl -I -A 'Slackbot' https://dev.livrolog.com/arnon | sed -n '1,20p'"
echo "  curl -I https://dev.livrolog.com/users/U-XXXX/shelf-image"
