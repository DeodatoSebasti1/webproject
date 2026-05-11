# Relatório Técnico UrbanTraffic

## 1. Introdução

Template base para o relatório final do projeto.

## 2. Enquadramento

- mobilidade urbana
- dificuldade em combinar rotas, mapas e tempo real

## 3. Problema

- fragmentação da informação de transportes
- baixa previsibilidade do utilizador

## 4. Público-alvo

- estudantes
- residentes urbanos
- utilizadores de transportes públicos

## 5. Objetivos

- centralizar pesquisa, rota, mapa e veículos
- melhorar experiência de deslocação

## 6. Requisitos funcionais

- pesquisa
- cálculo de rotas
- mapa
- realtime
- autenticação
- favoritos
- histórico
- backoffice

## 7. Requisitos não funcionais

- desempenho
- disponibilidade
- consistência visual
- compatibilidade com XAMPP/MySQL

## 8. Arquitetura do sistema

- frontend `public/`
- controllers `app/controllers/`
- services `app/services/`
- base de dados `urbandb`

## 9. Base de dados

- utilizadores
- sessões
- favoritos
- histórico
- cache
- GTFS
- eventos da aplicação

## 10. API REST

Referenciar [API.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/API.md)

## 11. Algoritmo de rotas

- GTFS estático
- diretas
- transbordos
- fallback
- cache

## 12. Integração SIG/mapa

- Mapbox GL
- visualização da rota
- stops
- veículo selecionado

## 13. Realtime

- API Carris Metropolitana
- enriquecimento da rota
- fallback/estimated/simulated

## 14. UI/UX

- identidade verde
- fluxo de pesquisa
- resultados com alternativas

## 15. Backoffice e estatísticas

- dashboard admin
- gráficos dinâmicos
- métricas de utilização

## 16. Testes de usabilidade

Referenciar [USABILITY_TESTS.md](/Applications/XAMPP/xamppfiles/htdocs/urban/docs/USABILITY_TESTS.md)

## 17. Limitações

- dependência de dados externos
- necessidade de GTFS completo

## 18. Trabalho futuro

- melhor previsão ETA
- notificações
- mobile-first refinement

## 19. Conclusão

- síntese do valor técnico e académico do UrbanTraffic
