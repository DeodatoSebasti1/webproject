# UrbanTraffic

UrbanTraffic é uma aplicação web em PHP/XAMPP para apoio à mobilidade urbana, com cálculo de rotas em transportes públicos, integração cartográfica, dados GTFS, realtime/fallback, autenticação, favoritos, histórico, cache e backoffice com estatísticas dinâmicas.

## Funcionalidades

- Pesquisa de origem/destino com geocoding
- Cálculo de rotas GTFS com alternativas e transbordos
- Visualização de rota em mapa
- Realtime/fallback para veículos e ETA
- Login, registo e sessões
- Favoritos e histórico
- Cache de rotas
- Backoffice/admin com gráficos e métricas

## Requisitos

- XAMPP com Apache + MySQL
- PHP 8+
- Base de dados MySQL/MariaDB
- Tabelas GTFS importadas em `urbandb`

## Instalação

1. Colocar o projeto em `/Applications/XAMPP/xamppfiles/htdocs/urban`
2. Garantir que Apache e MySQL estão ativos no XAMPP
3. Executar o setup:

```bash
open http://localhost/urban/setup_database.php
```

4. Se houver problemas com `localhost`, usar:

```bash
URBAN_DB_HOST=127.0.0.1
```

5. Importar os dados GTFS para a base `urbandb`:
   - `stops`
   - `routes`
   - `trips`
   - `stop_times`
   - `shapes`
   - `calendar`
   - `calendar_dates`

## Configuração da base `urbandb`

As credenciais são lidas de variáveis de ambiente:

- `URBAN_DB_HOST`
- `URBAN_DB_PORT`
- `URBAN_DB_NAME`
- `URBAN_DB_USER`
- `URBAN_DB_PASSWORD`

Fallback por omissão:

- host: `localhost`
- port: `3306`
- base: `urbandb`
- user: `root`

## Endpoints principais

- `/urban/public/api/search?q=...`
- `/urban/public/api/routes?...`
- `/urban/public/api/realtime?...`
- `/urban/public/api/auth?action=...`
- `/urban/public/api/user?action=...`
- `/urban/public/api/admin?action=...`

## Como correr localmente

- Home: `/urban/public/index.php`
- Resultados: `/urban/public/results.php?...`
- Linhas: `/urban/public/lines.php`
- Configurações: `/urban/public/configuracoes.php`
- Backoffice: `/urban/public/admin.php`

## Backoffice

O backoffice exige autenticação com utilizador de role `admin`.

Passos recomendados:

1. Criar conta normal no site em `/urban/public/index.php`
2. Promover a conta por SQL:

```sql
UPDATE users SET role = 'admin' WHERE email = 'teuemail@exemplo.com';
```

3. Fazer logout e login novamente
4. Aceder a `/urban/public/admin.php`

Se o acesso não funcionar, verificar:

- campo `role` na tabela `users`
- existência do token na `user_sessions`
- resposta de `/urban/public/api/auth?action=verify`
- MySQL ativo no XAMPP

Guia detalhado:

- [docs/ADMIN_GUIDE.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/ADMIN_GUIDE.md)

## Contas de teste

- Criar utilizadores pelo fluxo de registo
- Promover uma conta para `admin` na tabela `users`

## Estrutura documental

- [docs/API.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/API.md)
- [docs/DESIGN_SYSTEM.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/DESIGN_SYSTEM.md)
- [docs/USABILITY_TESTS.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/USABILITY_TESTS.md)
- [docs/RELATORIO_TECNICO.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/RELATORIO_TECNICO.md)
- [docs/POSTER_A3_CONTENT.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/POSTER_A3_CONTENT.md)
- [docs/VIDEO_SCRIPT.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/VIDEO_SCRIPT.md)
- [docs/RELEASE_CHECKLIST.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/RELEASE_CHECKLIST.md)
- [docs/uml/use_cases.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/uml/use_cases.md)
- [docs/uml/domain_model.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/uml/domain_model.md)

## Limitações conhecidas

- O realtime depende da disponibilidade da API externa
- Sem import GTFS completo, o motor de rotas não devolve itinerários reais
- Algumas métricas do backoffice dependem de utilização real da aplicação para ganharem significado
