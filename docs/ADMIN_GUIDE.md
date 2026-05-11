# Admin Guide

## Aceder ao painel admin

1. Criar uma conta normal no UrbanTraffic:
   - `/urban/public/index.php`
2. Promover a conta para `admin` no MySQL:

```sql
UPDATE users SET role = 'admin' WHERE email = 'teuemail@exemplo.com';
```

3. Fazer logout e login novamente para renovar a sessão.
4. Abrir:
   - `http://localhost/urban/public/admin.php`

## O que validar se não funcionar

- Confirmar que a coluna `role` existe na tabela `users`
- Confirmar que o utilizador tem `role = 'admin'`
- Confirmar que existe uma sessão válida em `user_sessions`
- Confirmar que `/urban/public/api/auth?action=verify` devolve o campo `role`
- Confirmar que o MySQL do XAMPP está ligado

## Endpoints úteis

- `/urban/public/api/admin?action=stats`
- `/urban/public/api/admin?action=recent_users`
- `/urban/public/api/admin?action=recent_searches`
- `/urban/public/api/admin?action=popular_routes`
- `/urban/public/api/admin?action=searches_by_day`

Todos exigem autenticação administrativa com token Bearer válido.

## Troubleshooting rápido

- `Sessão inválida ou expirada`
  - fazer logout e login novamente
- `Acesso restrito`
  - verificar o `role` na tabela `users`
- gráficos vazios
  - gerar atividade na app: pesquisas, login, favoritos e rotas
- erro de base de dados
  - correr `/urban/setup_database.php`
