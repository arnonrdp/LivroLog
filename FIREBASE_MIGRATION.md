# 🔄 Guia de Migração Firebase → MySQL

## 📋 Visão Geral

Este guia detalha o processo completo para migrar seus dados do Firebase/Firestore para o novo sistema Laravel + MySQL.

## 🛠️ Ferramentas Disponíveis

### 1. Descoberta de Dados

```bash
# Descobre dados Firebase existentes
php artisan firebase:discover
```

### 2. Migração Completa

```bash
# Migra todos os tipos de dados
php artisan firebase:import --file=/path/to/export.json

# Migra tipo específico
php artisan firebase:import --type=showcase --file=/path/to/export.json
```

### 3. Migração Showcase (especializada)

```bash
# Migra apenas dados do showcase
php artisan import:firestore-showcase --file=/path/to/export.json
```

## 📊 Processo de Migração

### Passo 1: Preparação

#### 1.1 Backup dos Dados Atuais

```bash
# Backup do banco atual
mysqldump -u root -p livrolog > backup_before_migration.sql

# Backup dos uploads (se houver)
cp -r storage/app/public storage/app/public_backup
```

#### 1.2 Verificar Estrutura Atual

```bash
# Descobrir dados Firebase
php artisan firebase:discover

# Ver estrutura das tabelas
php artisan db:show
```

### Passo 2: Exportar do Firebase

#### 2.1 Via Firebase CLI

```bash
# Instalar Firebase CLI
npm install -g firebase-tools

# Login
firebase login

# Exportar Firestore
firebase firestore:export ./firebase-export

# Converter para JSON (se necessário)
# Use scripts ou ferramentas como jq para processar
```

#### 2.2 Via Console Firebase

1. Acesse [Firebase Console](https://console.firebase.google.com)
2. Selecione seu projeto
3. Vá em **Firestore Database**
4. Use **Import/Export** ou **Export Data**
5. Baixe o arquivo JSON

#### 2.3 Via SDK/Script

```javascript
// Script Node.js para exportar dados
const admin = require('firebase-admin')
const fs = require('fs')

// Inicializar com suas credenciais
admin.initializeApp({
  credential: admin.credential.cert('path/to/serviceAccount.json')
})

const db = admin.firestore()

async function exportData() {
  const collections = ['users', 'books', 'showcase']
  const exportData = {}

  for (const collectionName of collections) {
    const snapshot = await db.collection(collectionName).get()
    exportData[collectionName] = []

    snapshot.docs.forEach((doc) => {
      exportData[collectionName].push({
        id: doc.id,
        ...doc.data()
      })
    })
  }

  fs.writeFileSync('firebase-export.json', JSON.stringify(exportData, null, 2))
  console.log('Export completed!')
}

exportData()
```

### Passo 3: Preparar Dados

#### 3.1 Validar Estrutura do JSON

```bash
# Testar com dry-run
php artisan firebase:import --dry-run --file=firebase-export.json

# Visualizar primeiros registros
head -20 firebase-export.json
```

#### 3.2 Formatos Suportados

**Formato Firestore Export:**

```json
{
  "documents": [
    {
      "name": "projects/PROJECT_ID/databases/(default)/documents/books/BOOK_ID",
      "fields": {
        "title": { "stringValue": "Dom Casmurro" },
        "authors": { "stringValue": "Machado de Assis" },
        "isbn": { "stringValue": "9788525406552" }
      }
    }
  ]
}
```

**Formato Personalizado:**

```json
{
  "users": [
    {
      "display_name": "João Silva",
      "email": "joao@example.com",
      "username": "joao_silva"
    }
  ],
  "books": [
    {
      "title": "Dom Casmurro",
      "authors": "Machado de Assis",
      "isbn": "9788525406552"
    }
  ],
  "showcase": [
    {
      "title": "O Cortiço",
      "authors": "Aluísio Azevedo",
      "active": true
    }
  ]
}
```

### Passo 4: Executar Migração

#### 4.1 Teste Preliminar

```bash
# Preview da migração
php artisan firebase:import --dry-run --file=firebase-export.json

# Migração de teste (sem limpar dados existentes)
php artisan firebase:import --file=firebase-export.json
```

#### 4.2 Migração Completa

```bash
# ⚠️ ATENÇÃO: Limpa dados existentes
php artisan firebase:import --clear --file=firebase-export.json

# Ou por tipo específico
php artisan firebase:import --type=users --clear --file=firebase-export.json
php artisan firebase:import --type=books --clear --file=firebase-export.json
php artisan firebase:import --type=showcase --clear --file=firebase-export.json
```

#### 4.3 Migração por Lotes

```bash
# Para arquivos grandes, use batch processing
php artisan firebase:import --batch-size=50 --file=firebase-export.json
```

### Passo 5: Validação

#### 5.1 Verificar Dados Importados

```bash
# Contar registros
php artisan tinker
>>> User::count()
>>> Book::count()
>>> Showcase::count()

# Verificar relacionamentos
>>> User::first()->books
>>> Book::first()->users
```

#### 5.2 Testar API

```bash
# Testar endpoints
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/books
curl http://localhost:8000/showcase
```

#### 5.3 Testar Frontend

```bash
# Iniciar aplicação
cd webapp
yarn dev

# Verificar se os dados aparecem corretamente
```

## 🔧 Resolução de Problemas

### Problema: JSON Inválido

```bash
# Validar JSON
python -m json.tool firebase-export.json

# Ou usar jq
jq . firebase-export.json
```

### Problema: Campos Ausentes

```bash
# Ver estrutura dos dados
php artisan firebase:discover --file=firebase-export.json

# Ajustar mapeamento no comando ImportFirebaseData.php
```

### Problema: Duplicatas

```bash
# Migração irá atualizar duplicatas automaticamente
# Para controle manual, use:
php artisan firebase:import --dry-run --file=firebase-export.json
```

### Problema: Memória Insuficiente

```bash
# Aumentar memory_limit no PHP
ini_set('memory_limit', '512M');

# Ou processar em lotes menores
php artisan firebase:import --batch-size=25 --file=firebase-export.json
```

## 📊 Mapeamento de Dados

### Users (Firebase → MySQL)

| Firebase        | MySQL               | Observações         |
| --------------- | ------------------- | ------------------- |
| `displayName`   | `display_name`      | Nome de exibição    |
| `email`         | `email`             | Email único         |
| `uid`           | `username`          | Identificador único |
| `emailVerified` | `email_verified_at` | Data de verificação |

### Books (Firebase → MySQL)

| Firebase            | MySQL       | Observações                         |
| ------------------- | ----------- | ----------------------------------- |
| `title`             | `title`     | Título do livro                     |
| `authors`           | `authors`   | Array → String separada por vírgula |
| `isbn`/`ISBN`       | `isbn`      | Identificador único                 |
| `thumbnail`/`image` | `thumbnail` | URL da capa                         |
| `language`/`lang`   | `language`  | Idioma (padrão: pt-BR)              |

### Showcase (Firebase → MySQL)

| Firebase             | MySQL         | Observações       |
| -------------------- | ------------- | ----------------- |
| `title`              | `title`       | Título            |
| `authors`            | `authors`     | Autores           |
| `active`/`is_active` | `is_active`   | Status ativo      |
| `order`              | `order_index` | Ordem de exibição |

## 🔄 Pós-Migração

### 1. Atualizar Frontend

```bash
# Remover dependências Firebase (se ainda existirem)
cd webapp
yarn remove firebase

# Verificar se todas as chamadas usam a nova API
grep -r "firebase" src/
```

### 2. Configurar Autenticação

```bash
# Resetar senhas dos usuários migrados
php artisan tinker
>>> User::all()->each(function($user) {
    $user->password = Hash::make('password123');
    $user->save();
});
```

### 3. Configurar Relacionamentos

```bash
# Vincular livros aos usuários (se dados existirem)
# Implementar lógica específica baseada nos dados migrados
```

### 4. Backup Final

```bash
# Backup após migração bem-sucedida
mysqldump -u root -p livrolog > backup_after_migration.sql
```

## 🎯 Comandos Úteis

```bash
# Ver todas as opções de migração
php artisan firebase:import --help

# Migração rápida para testes
php artisan firebase:import --file=sample.json --type=showcase

# Limpar apenas showcase
php artisan firebase:import --type=showcase --clear --file=empty.json

# Debug de dados
php artisan firebase:discover
```

## ✅ Checklist de Migração

- [ ] Backup dos dados atuais
- [ ] Export dos dados Firebase
- [ ] Validação do JSON exportado
- [ ] Teste com `--dry-run`
- [ ] Migração de showcase
- [ ] Migração de books
- [ ] Migração de users
- [ ] Validação dos dados importados
- [ ] Teste da API
- [ ] Teste do frontend
- [ ] Configuração de senhas
- [ ] Backup final
- [ ] Remoção de dependências Firebase
- [ ] Documentação das alterações

## 🆘 Suporte

Se encontrar problemas:

1. Execute `php artisan firebase:discover` para diagnóstico
2. Use `--dry-run` para testar sem alterações
3. Verifique os logs em `storage/logs/laravel.log`
4. Consulte a documentação da API em `/api/documentation`
