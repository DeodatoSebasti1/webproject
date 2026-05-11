# Release Checklist

## Código e estrutura

- repositório com código fonte completo
- `setup_database.php` incluído
- pasta `sql/` incluída
- pasta `docs/` incluída

## O que não incluir

- PDFs finais se o briefing proibir
- dados pessoais reais
- credenciais reais

## Preparação da release

- validar sintaxe PHP
- validar JS
- confirmar setup da BD
- confirmar import GTFS
- confirmar conta admin

## Instalação

- clonar/copiar para `htdocs/urban`
- executar `setup_database.php`
- importar GTFS
- abrir `public/index.php`

## Demo

- testar pesquisa
- testar rota
- testar realtime
- testar favoritos/histórico
- testar backoffice
