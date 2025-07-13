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

## 🚀 Próximos Passos

1. **Fazer Login** com as credenciais acima
2. **Criar uma Organização** de teste
3. **Criar um usuário GO** para a organização
4. **Testar Context Switching** assumindo o papel do GO
5. **Criar Lojas** e **Store Managers**

## 📝 Observações Importantes

- ⚠️ **Segurança**: Altere a senha padrão em produção
- 🔒 **Backup**: Faça backup das credenciais MASTER
- 🧪 **Testes**: Use o Context Switching para testar todos os níveis
- 📋 **Logs**: Todas as ações do MASTER são registradas no sistema