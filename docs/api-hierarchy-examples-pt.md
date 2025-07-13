# API Madnezz - Controle de Acesso Organizacional Hierárquico

## 📚 Documentação Swagger

Acesse a documentação completa em: `http://localhost:9000/api/documentation`

## 🔐 Autenticação

Primeiro faça login para obter o token JWT:

```bash
# Login como GO (Diretor)
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "go@madnezz.com",
    "password": "password"
  }'

# Login como GR (Gerente Regional)
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "gr1@madnezz.com",
    "password": "password"
  }'

# Login como Gerente de Loja
curl -X POST http://localhost:9000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "sm1@madnezz.com",
    "password": "password"
  }'
```

## 🏢 Contexto Organizacional

### Obter Contexto Organizacional
**Endpoint:** `GET /api/v1/organization/context`  
**Acesso:** Todos os usuários autenticados com posição organizacional

```bash
curl -X GET "http://localhost:9000/api/v1/organization/context" \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -H "Accept: application/json"
```

**Exemplo de Resposta:**
```json
{
  "success": true,
  "data": {
    "organization_id": "612b29b1-06a4-4885-ab98-de099feb6ecd",
    "organization_name": "Madnezz Corporation",
    "organization_code": "MADNEZZ",
    "position_level": "go",
    "organization_unit_id": "e1acb852-4489-43d3-847f-ec027d016ccf",
    "organization_unit_name": "Madnezz Corporation",
    "organization_unit_type": "company",
    "departments": ["administrative", "financial", "marketing", "operations", "trade", "macro"],
    "position_id": "a9da6ad7-8cf2-4f0a-a31a-b5ff49c39c8c"
  }
}
```

## 📊 Controle de Acesso Baseado em Hierarquia

### Nível GO (Diretor) - Nível Mais Alto
**Endpoint:** `GET /api/v1/organization/dashboard`  
**Acesso:** Apenas nível GO

```bash
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer {GO_TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Dashboard GO - visão geral da organização",
    "context": { ... }
  }
}
```

### Nível GR (Gerente Regional)
**Endpoint:** `GET /api/v1/regional/dashboard`  
**Acesso:** Nível GR e superiores (GR + GO)

```bash
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer {GR_TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Dashboard regional - visão geral da região",
    "context": { ... }
  }
}
```

### Nível Loja (Gerente de Loja)
**Endpoint:** `GET /api/v1/store/dashboard`  
**Acesso:** Todos os níveis (Gerente de Loja + GR + GO)

```bash
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer {SM_TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Dashboard da loja - dados específicos da loja",
    "context": { ... }
  }
}
```

## 🏢 Controle de Acesso Baseado em Departamento

### Relatórios do Departamento Administrativo
**Endpoint:** `GET /api/v1/reports/administrative`  
**Acesso:** Usuários com acesso ao departamento Administrativo

```bash
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Relatórios do departamento administrativo",
    "department": "administrative"
  }
}
```

**Resposta de Erro:**
```json
{
  "success": false,
  "message": "Acesso negado. Departamento necessário: administrative"
}
```

### Relatórios do Departamento Financeiro
**Endpoint:** `GET /api/v1/reports/financial`  
**Acesso:** Usuários com acesso ao departamento Financeiro

```bash
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Erro (GR sem acesso financeiro):**
```json
{
  "success": false,
  "message": "Acesso negado. Departamento necessário: financial"
}
```

## 🎯 Controle de Acesso Específico a Recursos

### Detalhes da Loja
**Endpoint:** `GET /api/v1/store/{store_id}/details`  
**Acesso:** Validação automática baseada na hierarquia e acesso à loja do usuário

```bash
curl -X GET "http://localhost:9000/api/v1/store/488f1116-41ce-4976-9b6f-90ccaf68bede/details" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Detalhes da loja: 488f1116-41ce-4976-9b6f-90ccaf68bede",
    "store_id": "488f1116-41ce-4976-9b6f-90ccaf68bede",
    "context": { ... }
  }
}
```

**Resposta de Erro:**
```json
{
  "success": false,
  "message": "Acesso negado a esta loja"
}
```

### Detalhes da Unidade Organizacional
**Endpoint:** `GET /api/v1/unit/{unit_id}/details`  
**Acesso:** Validação automática baseada na hierarquia e acesso à unidade do usuário

```bash
curl -X GET "http://localhost:9000/api/v1/unit/7d777412-9585-4a83-ab04-ebd28aae725e/details" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Erro:**
```json
{
  "success": false,
  "message": "Acesso negado a esta unidade organizacional"
}
```

## 🔀 Controle de Acesso Combinado

### Campanhas de Marketing Regionais
**Endpoint:** `GET /api/v1/campaigns/regional`  
**Acesso:** Nível GR e superiores + acesso ao departamento de Marketing

```bash
curl -X GET "http://localhost:9000/api/v1/campaigns/regional" \
  -H "Authorization: Bearer {GR_TOKEN}" \
  -H "Accept: application/json"
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "message": "Campanhas de marketing regionais (GR+ com depto Marketing)",
    "access": "Nível GR ou GO com acesso ao departamento de Marketing"
  }
}
```

## 🧪 Cenários de Teste

### Cenário 1: Usuário GO
```bash
# GO pode acessar todos os níveis
GO_TOKEN="..." # Obtido do login

# ✅ Deve funcionar - GO pode acessar dashboard GO
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Deve funcionar - GO pode acessar dashboard GR
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Deve funcionar - GO pode acessar dashboard da Loja
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $GO_TOKEN"

# ✅ Deve funcionar - GO tem todos os departamentos
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $GO_TOKEN"
```

### Cenário 2: Usuário GR
```bash
# GR tem acesso limitado
GR_TOKEN="..." # Obtido do login

# ❌ Deve falhar - GR não pode acessar dashboard GO
# Resposta: {"success":false,"message":"Nível hierárquico insuficiente. Necessário: go"}
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Deve funcionar - GR pode acessar dashboard GR
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Deve funcionar - GR pode acessar dashboard da Loja
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $GR_TOKEN"

# ❌ Deve falhar - GR não tem departamento Financeiro
# Resposta: {"success":false,"message":"Acesso negado. Departamento necessário: financial"}
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $GR_TOKEN"

# ✅ Deve funcionar - GR tem departamento Administrativo
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer $GR_TOKEN"
```

### Cenário 3: Gerente de Loja
```bash
# Gerente de Loja tem acesso mais limitado
SM_TOKEN="..." # Obtido do login

# ❌ Deve falhar - SM não pode acessar dashboard GO
# Resposta: {"success":false,"message":"Nível hierárquico insuficiente. Necessário: go"}
curl -X GET "http://localhost:9000/api/v1/organization/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Deve falhar - SM não pode acessar dashboard GR
# Resposta: {"success":false,"message":"Nível hierárquico insuficiente. Necessário: gr"}
curl -X GET "http://localhost:9000/api/v1/regional/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ✅ Deve funcionar - SM pode acessar dashboard da Loja
curl -X GET "http://localhost:9000/api/v1/store/dashboard" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Deve falhar - SM não tem departamento Financeiro
curl -X GET "http://localhost:9000/api/v1/reports/financial" \
  -H "Authorization: Bearer $SM_TOKEN"

# ❌ Deve falhar - SM não tem departamento Administrativo
curl -X GET "http://localhost:9000/api/v1/reports/administrative" \
  -H "Authorization: Bearer $SM_TOKEN"
```

## 📋 Matriz de Controle de Acesso

| Endpoint | GO | GR | Gerente de Loja |
|----------|----|----|-----------------|
| `/organization/context` | ✅ | ✅ | ✅ |
| `/organization/dashboard` | ✅ | ❌ | ❌ |
| `/regional/dashboard` | ✅ | ✅ | ❌ |
| `/store/dashboard` | ✅ | ✅ | ✅ |
| `/reports/administrative` | ✅ | ✅ | ❌ |
| `/reports/financial` | ✅ | ❌ | ❌ |
| `/campaigns/regional` | ✅ | ✅* | ❌ |

*Requer acesso ao departamento de Marketing

## 🔐 Mensagens de Erro em Português

### Erros de Autenticação
```json
{
  "success": false,
  "message": "Usuário não autenticado"
}
```

### Erros de Contexto Organizacional
```json
{
  "success": false,
  "message": "Usuário não possui posição ativa em nenhuma organização"
}
```

```json
{
  "success": false,
  "message": "Organização não encontrada ou inativa"
}
```

```json
{
  "success": false,
  "message": "Unidade organizacional não encontrada ou inativa"
}
```

### Erros de Hierarquia
```json
{
  "success": false,
  "message": "Nível hierárquico insuficiente. Necessário: go"
}
```

```json
{
  "success": false,
  "message": "Nível hierárquico insuficiente. Necessário: gr"
}
```

### Erros de Departamento
```json
{
  "success": false,
  "message": "Acesso negado. Departamento necessário: financial"
}
```

```json
{
  "success": false,
  "message": "Acesso negado. Departamento necessário: administrative"
}
```

### Erros de Recurso Específico
```json
{
  "success": false,
  "message": "Acesso negado a esta loja"
}
```

```json
{
  "success": false,
  "message": "Acesso negado a esta unidade organizacional"
}
```

## 🏗️ Notas da Arquitetura

### Stack de Middleware
1. `jwt.auth` - Validação de autenticação JWT
2. `org.context` - Injeção de contexto organizacional
3. `hierarchy.access:nivel,departamento` - Validação de hierarquia e departamento

### Níveis Hierárquicos
- **GO (Diretor)**: Nível mais alto, gerencia toda a organização
- **GR (Gerente Regional)**: Gerencia unidades regionais e lojas
- **Gerente de Loja**: Gerencia lojas individuais

### Departamentos
- **Administrative**: Administração geral
- **Financial**: Controle financeiro e contábil
- **Marketing**: Marketing e comunicação
- **Operations**: Operações e logística
- **Trade**: Trade marketing e vendas
- **Macro**: Planejamento macro estratégico

### Regras de Acesso
1. Níveis hierárquicos superiores herdam acesso dos níveis inferiores
2. Acesso por departamento é específico da função e não herdado
3. Acesso a recursos é automaticamente validado baseado na hierarquia organizacional
4. Usuários GO têm acesso a todos os departamentos por padrão
5. Usuários GR têm acesso a departamentos operacionais
6. Gerentes de Loja têm acesso apenas a departamentos específicos da loja

## 🔒 Funcionalidades de Segurança

- **Autenticação baseada em JWT** com expiração de token
- **Controle de acesso multicamadas** (hierarquia + departamento + recurso)
- **Injeção automática de contexto** para dados organizacionais
- **Arquitetura Domain-Driven Design** com separação adequada de responsabilidades
- **Value objects** para segurança de tipos e validação de regras de negócio
- **Padrão Repository** para abstração de acesso a dados