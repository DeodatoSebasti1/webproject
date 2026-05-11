# API UrbanTraffic

## `/api/search`

- Método: `GET`
- Parâmetros:
  - `q` string obrigatória
- Exemplo:
```http
GET /urban/public/api/search?q=lisboa
```
- Resposta:
```json
[
  {
    "display": "Lisboa, Portugal",
    "full": "Lisboa, Portugal",
    "type": "city",
    "class": "place",
    "lat": "38.7223",
    "lon": "-9.1393"
  }
]
```
- Erros possíveis:
  - query curta devolve `[]`
  - falha externa ativa fallback local

## `/api/routes`

- Método: `GET`
- Parâmetros:
  - `fromLat`, `fromLon`, `toLat`, `toLon`
  - ou `origin`, `dest`
  - `departureTime` opcional
- Exemplo:
```http
GET /urban/public/api/routes?fromLat=38.72&fromLon=-9.13&toLat=38.76&toLon=-9.09
```
- Resposta:
```json
{
  "status": "success",
  "routes": [],
  "origin": {},
  "destination": {},
  "walk_info": {}
}
```
- Erros possíveis:
  - coordenadas em falta
  - paragens não encontradas
  - GTFS incompleto
  - erro interno no cálculo

## `/api/realtime`

- Método: `GET`
- Ações:
  - `vehicles`
  - `summary`
  - `route`
  - `vehicle`
  - `stop`
  - `trip_eta`
- Exemplo:
```http
GET /urban/public/api/realtime?action=summary
```
- Resposta:
```json
{
  "status": "success",
  "source": "realtime",
  "data": {}
}
```
- Erros possíveis:
  - ação inválida
  - API externa indisponível
  - fallback/simulated quando não há realtime

## `/api/auth`

- Método:
  - `POST` para `register`, `login`, `logout`
  - `GET` para `verify`, `profile`
- Ações:
  - `register`
  - `login`
  - `logout`
  - `verify`
  - `profile`
- Exemplo:
```http
POST /urban/public/api/auth?action=login
Content-Type: application/json

{"email":"demo@urban.pt","password":"123456"}
```
- Resposta:
```json
{
  "status": "success",
  "user": {
    "id": 1,
    "email": "demo@urban.pt",
    "name": "Demo",
    "role": "admin"
  },
  "token": "..."
}
```
- Erros possíveis:
  - dados incompletos
  - email já existente
  - credenciais inválidas
  - sessão expirada

## `/api/user`

- Método:
  - `GET`
  - `POST`
- Ações:
  - `favorites`
  - `add_favorite`
  - `remove_favorite`
  - `is_favorite`
  - `history`
  - `add_history`
- Exemplo:
```http
POST /urban/public/api/user?action=add_favorite
Authorization: Bearer TOKEN
Content-Type: application/json
```
- Resposta:
```json
{
  "status": "success",
  "message": "Rota adicionada aos favoritos."
}
```
- Erros possíveis:
  - token inválido
  - dados incompletos
  - erro SQL

## `/api/admin`

- Método: `GET`
- Requer: `Authorization: Bearer TOKEN` com `role = admin`
- Ações:
  - `stats`
  - `recent_users`
  - `recent_searches`
  - `popular_routes`
  - `searches_by_day`
  - `users_by_day`
  - `favorites_by_route`
  - `dashboard`
- Exemplo:
```http
GET /urban/public/api/admin?action=dashboard
Authorization: Bearer TOKEN
```
- Resposta:
```json
{
  "status": "success",
  "data": {
    "stats": {},
    "recent_users": [],
    "recent_searches": []
  }
}
```
- Erros possíveis:
  - 403 se não for admin
  - ação inválida
  - tabelas ainda sem dados
