#!/bin/bash

# LivroLog Frontend Smoke Tests
# Quick validation of frontend application after deployment

set -e  # Exit on any error

# Configuration
WEB_URL="${WEB_URL:-http://localhost:8001}"
TIMEOUT="${TIMEOUT:-10}"
RETRIES="${RETRIES:-3}"
RETRY_DELAY="${RETRY_DELAY:-2}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
    FAILED_TESTS+=("$1")
}

log_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# HTTP request with retry logic
http_request() {
    local url="$1"
    local expected_status="${2:-200}"
    local method="${3:-GET}"
    
    for i in $(seq 1 $RETRIES); do
        local response
        local status
        
        response=$(curl -s -w "%{http_code}" -X "$method" --connect-timeout "$TIMEOUT" -L "$url" 2>/dev/null || echo "000")
        status="${response: -3}"
        body="${response%???}"
        
        if [[ "$status" == "$expected_status" ]]; then
            echo "$body"
            return 0
        fi
        
        if [[ $i -lt $RETRIES ]]; then
            log_warning "Request failed (attempt $i/$RETRIES), retrying in ${RETRY_DELAY}s..."
            sleep $RETRY_DELAY
        fi
    done
    
    echo "HTTP_ERROR_$status"
    return 1
}

# Check if content contains specific patterns
contains_pattern() {
    local content="$1"
    local pattern="$2"
    
    if echo "$content" | grep -qi "$pattern"; then
        return 0
    else
        return 1
    fi
}

# Test functions
test_homepage_loads() {
    log_info "Testing homepage loading..."
    
    local response
    response=$(http_request "$WEB_URL" 200)
    
    if [[ $? -eq 0 ]]; then
        if contains_pattern "$response" "<!DOCTYPE html" && contains_pattern "$response" "<html"; then
            log_success "Homepage - HTML structure valid"
        else
            log_error "Homepage - Invalid HTML structure"
        fi
    else
        log_error "Homepage - Request failed: $response"
    fi
}

test_app_title() {
    log_info "Testing app title and meta tags..."
    
    local response
    response=$(http_request "$WEB_URL" 200)
    
    if [[ $? -eq 0 ]]; then
        if contains_pattern "$response" "<title>" && (contains_pattern "$response" "LivroLog" || contains_pattern "$response" "livrolog"); then
            log_success "App title - Present and contains app name"
        else
            log_warning "App title - Missing or doesn't contain app name"
        fi
        
        if contains_pattern "$response" "<meta.*viewport"; then
            log_success "Viewport meta tag - Present"
        else
            log_warning "Viewport meta tag - Missing"
        fi
    else
        log_error "App title test - Cannot retrieve homepage"
    fi
}

test_javascript_bundle() {
    log_info "Testing JavaScript bundle loading..."
    
    local response
    response=$(http_request "$WEB_URL" 200)
    
    if [[ $? -eq 0 ]]; then
        # Look for script tags with .js files
        if contains_pattern "$response" "<script.*\.js"; then
            # Extract JS file paths and test one
            local js_file
            js_file=$(echo "$response" | grep -o 'src="[^"]*\.js[^"]*"' | head -1 | sed 's/src="//;s/"//')
            
            if [[ -n "$js_file" ]]; then
                # Handle relative URLs
                if [[ "$js_file" == /* ]]; then
                    js_file="$WEB_URL$js_file"
                elif [[ "$js_file" != http* ]]; then
                    js_file="$WEB_URL/$js_file"
                fi
                
                local js_response
                js_response=$(http_request "$js_file" 200)
                
                if [[ $? -eq 0 ]]; then
                    log_success "JavaScript bundle - Accessible"
                else
                    log_error "JavaScript bundle - Not accessible: $js_file"
                fi
            else
                log_warning "JavaScript bundle - No JS files found in HTML"
            fi
        else
            log_warning "JavaScript bundle - No script tags found"
        fi
    else
        log_error "JavaScript bundle test - Cannot retrieve homepage"
    fi
}

test_css_styles() {
    log_info "Testing CSS styles loading..."
    
    local response
    response=$(http_request "$WEB_URL" 200)
    
    if [[ $? -eq 0 ]]; then
        # Look for link tags with CSS files
        if contains_pattern "$response" "rel=\"stylesheet\"" || contains_pattern "$response" "\.css"; then
            # Extract CSS file paths and test one
            local css_file
            css_file=$(echo "$response" | grep -o 'href="[^"]*\.css[^"]*"' | head -1 | sed 's/href="//;s/"//')
            
            if [[ -n "$css_file" ]]; then
                # Handle relative URLs
                if [[ "$css_file" == /* ]]; then
                    css_file="$WEB_URL$css_file"
                elif [[ "$css_file" != http* ]]; then
                    css_file="$WEB_URL/$css_file"
                fi
                
                local css_response
                css_response=$(http_request "$css_file" 200)
                
                if [[ $? -eq 0 ]]; then
                    log_success "CSS styles - Accessible"
                else
                    log_error "CSS styles - Not accessible: $css_file"
                fi
            else
                log_warning "CSS styles - No CSS files found in HTML"
            fi
        else
            log_warning "CSS styles - No stylesheet links found"
        fi
    else
        log_error "CSS styles test - Cannot retrieve homepage"
    fi
}

test_spa_routing() {
    log_info "Testing SPA routing (404 handling)..."
    
    # Test a route that should be handled by the SPA router
    local response
    response=$(http_request "$WEB_URL/login" 200)
    
    if [[ $? -eq 0 ]]; then
        if contains_pattern "$response" "<!DOCTYPE html" && contains_pattern "$response" "<html"; then
            log_success "SPA routing - Routes served with HTML"
        else
            log_error "SPA routing - Routes not properly handled"
        fi
    else
        # Some configs might return 404 for unknown routes, which is also valid
        local status
        status="${response#*_}"
        if [[ "$status" == "404" ]]; then
            log_warning "SPA routing - Returns 404 (may need fallback config)"
        else
            log_error "SPA routing - Unexpected response: $response"
        fi
    fi
}

test_favicon() {
    log_info "Testing favicon..."
    
    local response
    response=$(http_request "$WEB_URL/favicon.ico" 200)
    
    if [[ $? -eq 0 ]]; then
        log_success "Favicon - Available"
    else
        log_warning "Favicon - Not available (non-critical)"
    fi
}

test_security_headers() {
    log_info "Testing security headers..."
    
    local headers
    headers=$(curl -s -I --connect-timeout "$TIMEOUT" "$WEB_URL" 2>/dev/null || echo "")
    
    local security_score=0
    
    if echo "$headers" | grep -qi "x-frame-options"; then
        ((security_score++))
    fi
    
    if echo "$headers" | grep -qi "x-content-type-options"; then
        ((security_score++))
    fi
    
    if echo "$headers" | grep -qi "x-xss-protection"; then
        ((security_score++))
    fi
    
    if [[ $security_score -ge 2 ]]; then
        log_success "Security headers - Good coverage ($security_score/3)"
    elif [[ $security_score -ge 1 ]]; then
        log_warning "Security headers - Basic coverage ($security_score/3)"
    else
        log_warning "Security headers - Missing or minimal coverage"
    fi
}

# Main execution
main() {
    echo "========================================="
    echo "🌐 LivroLog Frontend Smoke Tests"
    echo "========================================="
    echo "Web URL: $WEB_URL"
    echo "Timeout: ${TIMEOUT}s"
    echo "Retries: $RETRIES"
    echo "========================================="
    echo ""
    
    # Check if frontend is reachable
    log_info "Checking frontend connectivity..."
    if ! curl -s --connect-timeout 5 "$WEB_URL" >/dev/null 2>&1; then
        log_error "Cannot reach frontend at $WEB_URL"
        exit 1
    fi
    log_success "Frontend is reachable"
    echo ""
    
    # Run tests
    test_homepage_loads
    test_app_title
    test_javascript_bundle
    test_css_styles
    test_spa_routing
    test_favicon
    test_security_headers
    
    echo ""
    echo "========================================="
    echo "📊 Test Results Summary"
    echo "========================================="
    echo "✅ Passed: $TESTS_PASSED"
    echo "❌ Failed: $TESTS_FAILED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        echo ""
        echo "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            echo "  - $test"
        done
        echo ""
        echo "❌ Frontend smoke tests FAILED"
        exit 1
    else
        echo ""
        echo "🎉 All frontend smoke tests PASSED"
        exit 0
    fi
}

# Script entry point
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi