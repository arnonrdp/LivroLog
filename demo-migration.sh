#!/bin/bash

# 🚀 Script de Demonstração da Migração Firebase
# Este script demonstra todo o processo de migração de dados do Firebase

echo "🔥 === DEMONSTRAÇÃO MIGRAÇÃO FIREBASE → MYSQL === 🔥"
echo ""

# Verificar se o Docker está rodando
echo "📋 1. Verificando ambiente..."
if ! docker compose ps | grep -q "Up"; then
    echo "❌ Docker containers não estão rodando. Execute 'docker compose up -d' primeiro."
    exit 1
fi
echo "✅ Docker containers estão rodando"
echo ""

# Descobrir dados Firebase existentes
echo "📋 2. Descobrindo dados Firebase..."
docker compose exec api php artisan firebase:discover
echo ""

# Testar migração com dados de exemplo (formato personalizado)
echo "📋 3. Testando migração com dados personalizados..."
echo "🔍 Preview dos dados (dry-run):"
docker compose exec api php artisan firebase:import --dry-run --file=/var/www/html/firebase-sample.json
echo ""

# Testar migração com formato Firestore real
echo "📋 4. Testando migração com formato Firestore..."
echo "🔍 Preview dos dados Firestore (dry-run):"
docker compose exec api php artisan firebase:import --dry-run --file=/var/www/html/firestore-export.json
echo ""

# Executar migração real do showcase
echo "📋 5. Executando migração real (showcase)..."
docker compose exec api php artisan firebase:import --type=showcase --file=/var/www/html/firestore-export.json
echo ""

# Verificar dados importados
echo "📋 6. Verificando dados importados..."
echo "📊 Contando registros na base de dados:"
docker compose exec api php artisan tinker --execute="
echo 'Users: ' . App\Models\User::count() . PHP_EOL;
echo 'Books: ' . App\Models\Book::count() . PHP_EOL;
echo 'Showcase: ' . App\Models\Showcase::count() . PHP_EOL;
echo PHP_EOL;
echo 'Últimos itens do showcase:' . PHP_EOL;
App\Models\Showcase::latest()->take(3)->get(['title', 'authors'])->each(function(\$item) { 
    echo '- ' . \$item->title . ' by ' . \$item->authors . PHP_EOL; 
});
"
echo ""

# Testar API
echo "📋 7. Testando API..."
echo "🌐 Endpoint /api/showcase:"
curl -s http://localhost:8000/api/showcase | python3 -c "
import json, sys
data = json.load(sys.stdin)
print(f'Total de livros no showcase: {len(data)}')
for book in data[-2:]:
    print(f'- {book[\"title\"]} por {book[\"authors\"]}')
"
echo ""

echo "🎯 === PRÓXIMOS PASSOS PARA MIGRAÇÃO REAL === 🎯"
echo ""
echo "1. 📥 Exportar seus dados reais do Firebase:"
echo "   - Via CLI: firebase firestore:export ./export"
echo "   - Via Console Firebase: Project Settings > Service Accounts"
echo ""
echo "2. 🔄 Executar migração completa:"
echo "   - Teste: php artisan firebase:import --dry-run --file=export.json"
echo "   - Real: php artisan firebase:import --clear --file=export.json"
echo ""
echo "3. ✅ Validar migração:"
echo "   - Contar registros: php artisan tinker"
echo "   - Testar API: curl http://localhost:8000/api/showcase"
echo "   - Testar frontend: yarn dev"
echo ""
echo "📚 Documentação completa: FIREBASE_MIGRATION.md"
echo "🆘 Para problemas: php artisan firebase:discover"
echo ""
echo "✨ Migração demonstrada com sucesso!"
