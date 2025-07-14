# 🔑 Credenciais do Usuário MASTER

## 📧 Login e Senha

```
Email: master@madnezz.com
Senha: Master@123
```

## 👑 Informações do Usuário MASTER

- **Nome**: Master Admin
- **Papel**: MASTER (acesso total ao sistema)
- **Status**: Ativo
- **Telefone**: +55 11 99999-9999
- **Organização**: Nenhuma (MASTER não pertence a organização específica)
- **Loja**: Nenhuma (MASTER não pertence a loja específica)
- **Permissões**: `["*"]` (todas as permissões)

## 🛠️ Script SQL para Inserir Manualmente

Se você quiser inserir diretamente no banco de dados, use este SQL:

```sql
INSERT INTO users_ddd (
    id, 
    name, 
    email, 
    password, 
    hierarchy_role, 
    status, 
    organization_id, 
    store_id, 
    phone, 
    permissions, 
    context_data,
    email_verified_at, 
    last_login_at, 
    created_at, 
    updated_at
) VALUES (
    '550e8400-e29b-41d4-a716-446655440000',
    'Master Admin',
    'master@madnezz.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Hash para 'Master@123'
    'MASTER',
    'active',
    NULL,
    NULL,
    '+55 11 99999-9999',
    '["*"]',
    NULL,
    datetime('now'),
    NULL,
    datetime('now'),
    datetime('now')
);
```

## 💡 Funcionalidades do Usuário MASTER

O usuário MASTER tem acesso completo ao sistema e pode:

### 🏢 **Criar Organizações**
- Criar novas organizações
- Atribuir usuários GO às organizações
- Visualizar todas as organizações do sistema

### 🏪 **Criar Lojas**
- Criar lojas em qualquer organização
- Atribuir gerentes às lojas
- Visualizar todas as lojas do sistema

### 🔄 **Context Switching (Assumir Papéis)**
- Assumir o papel de GO de qualquer organização
- Assumir o papel de GR de qualquer organização
- Assumir o papel de Store Manager de qualquer loja
- Testar funcionalidades de cada nível hierárquico

### 👥 **Gestão de Usuários**
- Criar usuários em todos os níveis (GO, GR, Store Manager)
- Visualizar todos os usuários do sistema
- Gerenciar permissões e acessos

### 📊 **Dashboards e Relatórios**
- Acessar dashboard global do sistema
- Visualizar métricas de todas as organizações
- Gerar relatórios completos

## 🎯 Hierarquia do Sistema

```
MASTER (Super Admin) ← Você está aqui
├── Visualiza TODAS organizações
├── Pode "assumir" qualquer nível de acesso para testes
├── Cria organizações e usuários GO
└── Dashboard administrativo completo

    └── GO (Diretor Geral)
        ├── Gerencia SUA organização
        ├── Visualiza todos GRs da organização
        ├── Cria lojas e usuários GR
        └── Dashboard organizacional

            └── GR (Gerente Regional)
                ├── Gerencia SUA região
                ├── Visualiza lojas da região
                ├── Cria usuários Store Manager
                └── Dashboard regional

                    └── STORE_MANAGER (Gerente de Loja)
                        ├── Gerencia SUA loja
                        ├── Visualiza equipe da loja
                        └── Dashboard da loja
```

### 📊 Detalhamento dos Papéis Hierárquicos

#### 👑 **MASTER** (Administrador do Sistema)
- **Escopo**: Sistema completo
- **Permissões**: Acesso total sem restrições
- **Responsabilidades**:
  - Criar e gerenciar organizações
  - Definir usuários GO para cada organização
  - Monitorar todo o sistema
  - Configurar parâmetros globais
  - Context switching para qualquer nível
- **Dashboards**: 
  - Visão global de todas organizações
  - Métricas consolidadas do sistema
  - Logs de auditoria completos

#### 🏢 **GO** (General Officer / Diretor Geral)
- **Escopo**: Uma organização específica
- **Permissões**: Controle total sobre sua organização
- **Responsabilidades**:
  - Criar e gerenciar regiões
  - Definir usuários GR para cada região
  - Criar lojas e atribuí-las a regiões
  - Gerenciar políticas organizacionais
  - Context switching para GR ou Store Manager de sua organização
- **Dashboards**:
  - Visão completa da organização
  - Performance por região
  - Métricas consolidadas de todas as lojas

#### 🌎 **GR** (Regional Manager / Gerente Regional)
- **Escopo**: Uma região específica dentro da organização
- **Permissões**: Controle sobre lojas de sua região
- **Responsabilidades**:
  - Supervisionar lojas da região
  - Criar e gerenciar Store Managers
  - Implementar estratégias regionais
  - Monitorar performance das lojas
  - Context switching para Store Manager de sua região
- **Dashboards**:
  - Visão regional consolidada
  - Comparativo entre lojas
  - Indicadores de performance regional

#### 🏪 **STORE_MANAGER** (Gerente de Loja)
- **Escopo**: Uma loja específica
- **Permissões**: Controle operacional da loja
- **Responsabilidades**:
  - Gerenciar operações diárias
  - Coordenar equipe da loja
  - Executar tarefas operacionais
  - Reportar para o GR
- **Dashboards**:
  - Métricas da loja
  - Performance da equipe
  - Indicadores operacionais

## 🔄 Context Switching (Mudança de Contexto)

O MASTER pode assumir o papel de qualquer usuário para testar funcionalidades:

### Exemplo de API para Context Switching:
```bash
# Assumir papel de GO
POST /api/v1/users/switch-context
{
  "target_role": "GO",
  "organization_id": "uuid-da-organizacao"
}

# Assumir papel de GR
POST /api/v1/users/switch-context
{
  "target_role": "GR",
  "organization_id": "uuid-da-organizacao",
  "region_id": "uuid-da-regiao"
}

# Assumir papel de Store Manager
POST /api/v1/users/switch-context
{
  "target_role": "STORE_MANAGER",
  "store_id": "uuid-da-loja"
}

# Voltar ao contexto MASTER
POST /api/v1/users/switch-context
{
  "target_role": "MASTER"
}
```

## 🛠️ APIs Principais por Papel

### MASTER APIs:
```bash
# Criar Organização
POST /api/v1/organizations
{
  "name": "Madnezz Brasil",
  "code": "BR001"
}

# Criar usuário GO
POST /api/v1/users
{
  "name": "João Silva",
  "email": "joao.silva@madnezz.com",
  "hierarchy_role": "GO",
  "organization_id": "uuid-organizacao"
}

# Listar todas organizações
GET /api/v1/organizations

# Dashboard global
GET /api/v1/dashboard/master
```

### GO APIs:
```bash
# Criar Região
POST /api/v1/regions
{
  "name": "Região Sul",
  "organization_id": "uuid-organizacao"
}

# Criar usuário GR
POST /api/v1/users
{
  "name": "Maria Santos",
  "email": "maria.santos@madnezz.com",
  "hierarchy_role": "GR",
  "organization_id": "uuid-organizacao",
  "region_id": "uuid-regiao"
}

# Criar Loja
POST /api/v1/stores
{
  "name": "Loja Centro SP",
  "code": "SP001",
  "region_id": "uuid-regiao"
}
```

### GR APIs:
```bash
# Criar Store Manager
POST /api/v1/users
{
  "name": "Pedro Costa",
  "email": "pedro.costa@madnezz.com",
  "hierarchy_role": "STORE_MANAGER",
  "store_id": "uuid-loja"
}

# Listar lojas da região
GET /api/v1/stores?region_id=uuid-regiao

# Dashboard regional
GET /api/v1/dashboard/regional/{region_id}
```

## 🚀 Próximos Passos

1. **Fazer Login** com as credenciais acima
   ```bash
   POST /api/v1/auth/login
   {
     "email": "master@madnezz.com",
     "password": "Master@123"
   }
   ```

2. **Criar uma Organização** de teste
   - Use a API de criação de organização
   - Defina nome e código únicos

3. **Criar um usuário GO** para a organização
   - Associe o GO à organização criada
   - Defina permissões organizacionais

4. **Testar Context Switching** assumindo o papel do GO
   - Use a API de switch-context
   - Verifique que as permissões mudaram

5. **Criar Regiões, Lojas** e **Store Managers**
   - Como GO, crie regiões
   - Como GO ou GR, crie lojas
   - Como GR, crie Store Managers

## 📊 Fluxo de Criação Hierárquica

```
1. MASTER cria Organização
   ↓
2. MASTER cria GO para a Organização
   ↓
3. GO cria Regiões
   ↓
4. GO cria GRs para as Regiões
   ↓
5. GO/GR criam Lojas
   ↓
6. GR cria Store Managers para as Lojas
```

## 🔍 Validações Importantes

- **GO** só pode ser criado pelo MASTER
- **GR** só pode ser criado por GO da mesma organização
- **Store Manager** só pode ser criado por GR da mesma região
- **Context Switching** respeita a hierarquia (não pode assumir papel superior)

## 📝 Observações Importantes

- ⚠️ **Segurança**: Altere a senha padrão em produção
- 🔒 **Backup**: Faça backup das credenciais MASTER
- 🧪 **Testes**: Use o Context Switching para testar todos os níveis
- 📋 **Logs**: Todas as ações do MASTER são registradas no sistema
- 🔐 **Tokens JWT**: Incluem informações de hierarquia e contexto atual
- 📡 **Headers**: Após context switch, use o header `X-Context-Role` nas requisições