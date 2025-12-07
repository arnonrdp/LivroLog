# Amazon PA-API - Roadmap

### Backend

- [x] **AmazonBooksProvider**: Integração completa PA-API v5

  - SearchItems (busca por ISBN, título, autor)
  - GetVariations (edições alternativas)
  - GetItems (batch até 10 ASINs)
  - 17 campos de dados extraídos
  - 6 regiões (BR, US, UK, CA, DE, FR)
  - Rate limiting, paginação, filtros

- [x] **AmazonEnrichmentService**: Descoberta de ASIN

  - Métodos reutilizáveis para busca Amazon
  - Validação de ISBN normalizada
  - Rate limiting e error handling

- [x] **Endpoints API**:
  - GET /books/{book}/editions
  - PUT /user/books/{book}/replace

### Frontend

- [x] **ChangeCoverDialog**: Seletor de edições via grid
- [x] **ReplaceBookDialog**: Substituição via busca
- [x] **BookDialog**: Integração com troca de capa
- [x] **Stores**: Métodos getBookEditions() e replaceUserBook()
- [x] **Traduções**: 15 chaves em 4 idiomas

### Configuração

- [x] Config `enabled` em services.php
- [x] Variável AMAZON_PA_API_ENABLED em .env.example
- [x] Credenciais configuradas em `.env`
- [x] **PA-API ativa e funcionando** ✓

**Estatísticas**: +1.957 linhas / -549 linhas | 21 arquivos | 10 commits

## Phase 2: Eliminação do Web Scraping

- [x] Deletar métodos obsoletos de web scraping
- [x] Simplificar para usar apenas PA-API
- [x] Remover imports não usados
- [x] Implementar similaridade de título (similar_text)
- [x] Validar match de autores quando não há ISBN
- [x] Normalização de ISBN, título e autores

## Phase 3: Recursos Avançados (Opcional)

### 3.1 GetItems Batch (3h)

- [x] Método getItems() implementado
- [ ] Command books:update-amazon-data
- [ ] Agendar job diário

### 3.2 Customer Reviews

- [ ] Extrair e exibir ratings da Amazon

### 3.3 Browse Nodes (Categorização)

- [ ] Implementar getBrowseNodes()
- [ ] Classificação automática por gênero

## Próximos Passos

Todos os itens críticos foram concluídos! ✅

**Opcional**: Implementar recursos avançados da Phase 3 conforme necessidade.
