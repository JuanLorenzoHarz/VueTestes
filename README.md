# Nexus — Pesquisa empresarial (v0.6)

Prova de conceito independente criada para estudar **Vue 3, TypeScript, PHP, API REST e MySQL**, explorando pesquisa empresarial, vínculos societários, dados financeiros e relatórios. Não é produto oficial nem tem vínculo com Gannet ou LegalSearch.

> **O design minimalista é uma escolha pessoal minha, baseada em acessibilidade e usabilidade.**
> Prefiro uma interface legível, previsível e fácil de navegar a efeitos visuais. Navegação por teclado, foco visível, organização e orientação são requisitos do projeto.

O sistema usa **exclusivamente dados fictícios**, sem consulta real a Serasa, SPC, Quod ou outros bureaus. Os avisos de simulação ficam concentrados **no rodapé da aplicação e no rodapé do relatório**, não repetidos em cada campo ou seção. Não utilize CPF/CNPJ real nem exponha este protótipo à internet.

## Uso e comportamento

1. Pesquise uma empresa pelo nome/código ou escolha uma entidade da lista.
2. **Ao selecionar uma empresa, a verificação é executada e registrada automaticamente**. O usuário não precisa clicar em “registrar histórico”.
3. Explore as abas **Visão geral**, **Árvore societária**, **Relacionamentos**, **Fontes e registros**, **Relatório** e **Histórico**.
4. O histórico apresenta as verificações recentes com data e empresa; o relatório consolida informações e pode ser impresso ou salvo em PDF.

A interface não inclui botões de execução técnica, cenários de timeout, métricas HTTP nem um laboratório de API. Essas funcionalidades continuam disponíveis internamente nos serviços PHP e nos testes, para demonstrar integração REST e tratamento de falhas sem acrescentar complexidade visual à experiência de quem consulta.

## Stack e arquitetura

| Camada | Tecnologias | Responsabilidade |
| --- | --- | --- |
| Frontend | Vue 3, TypeScript, Vite | Pesquisa, navegação, árvore, relatórios e histórico |
| Backend | PHP 8.3, PDO, cURL | Rotas REST, regras de negócio, integração, validação e auditoria |
| Banco | MySQL 8 | Empresas, sócios, vínculos, registros, indicadores e histórico |
| Serviços de estudo | PHP HTTP isolado | Dois fornecedores simulados de informações de crédito |
| Ambiente | Docker Compose | Execução local e testes |

**Registro automático e sem efeitos colaterais em GET:** ao selecionar uma empresa, o cliente solicita seu perfil por GET e envia um único POST para `POST /api/empresas/{id}/verificacoes`. Esse endpoint registra a consulta, executa os provedores com comportamento normal e devolve o perfil atualizado. O histórico é então recarregado. Assim, visitar o endpoint GET de um relatório não gera novos registros por engano.

Se um fornecedor estiver indisponível, o sistema preserva indicadores anteriores, quando existirem, e sinaliza a atualização incompleta. Não soma nem faz média de scores de diferentes fontes. Não calcula decisão automatizada de crédito.

## Executar localmente

Com Docker e Docker Compose instalados, na pasta raiz:

```bash
docker compose up --build
```

Abra **http://localhost:5173**. A API fica em `http://localhost:8080`, com `GET /api/health` para verificar seu estado. MySQL e frontend são expostos apenas no loopback do computador; os serviços de fornecedores só são acessíveis na rede interna do Docker.

Se essas portas estiverem ocupadas, crie um `.env` local (ignorado pelo Git) com `NEXUS_FRONTEND_PORT=5174`, `NEXUS_API_PORT=8081` e `NEXUS_MYSQL_PORT=3308`, uma variável por linha. Nesse caso, abra **http://localhost:5174**. Sem essas variáveis, as portas padrão permanecem iguais.

Cliques repetidos durante uma seleção compartilham a operação em andamento. Ao alternar entre empresas, uma verificação ainda pendente da mesma empresa é reutilizada; respostas antigas não substituem o perfil atual. Trocar abas, atualizar o relatório ou pesquisar mantendo a empresa selecionada não cria novas consultas. Reabrir uma empresa após a conclusão é uma nova ação e gera novo registro. Essa proteção é local à instância da interface, não uma chave de idempotência para clientes externos da API.

O histórico é recarregado após cada tentativa de verificação, inclusive quando a seleção já mudou ou a resposta falhou: o banco pode ter registrado a consulta antes da falha. Erros nessa atualização preservam os registros exibidos e apresentam uma mensagem discreta. Os avatares usam iniciais, dimensões fixas e proporção quadrada; não há fotos de participantes nesta versão.

Para encerrar sem perder os dados: `docker compose down`. **`docker compose down -v` apaga o banco e o histórico**, pois remove o volume.

### Atualização de versões anteriores

Não há nova alteração de esquema na v0.6. Se estiver vindo da Parte 3, 4A, 4B ou v0.5, pode manter o volume MySQL. Para uma instalação v0.1 ou v0.2 sem as tabelas de integração, aplique `infra/mysql/02-integracoes.sql` ao banco existente. O nome da pasta pode alterar o projeto/volume padrão do Compose; reaproveitar um volume antigo exige o mesmo nome de projeto ou migração dos dados.

Esta revisão explicita `SET NAMES utf8mb4` nos scripts de inicialização. Se um banco existente apresentar nomes como “LogÃ­stica”, aplique `infra/mysql/03-corrigir-codificacao.sql`: a correção compara os bytes exatos dos textos de demonstração afetados, preserva valores personalizados e não apaga consultas. Pode ser reaplicada sem modificar textos já corrigidos. Com o Compose em execução:

```bash
docker compose exec mysql sh -c 'mysql -unexus -pnexus_local nexus < /docker-entrypoint-initdb.d/03-corrigir-codificacao.sql'
```

## Relatório e PDF

Selecione uma empresa, abra **Relatório** e use **Imprimir / salvar PDF**. Escolha A4 e “Salvar como PDF” no navegador. O documento inclui identificação, quadro societário, indicadores, origem e datas dos dados, registros jurídicos, situação das fontes e ressalvas. O rodapé do PDF indica expressamente que os dados são fictícios. Não existe endpoint que gere arquivo PDF no servidor: a impressão utiliza CSS e `window.print()`.

## API REST

| Método | Endpoint | Uso |
| --- | --- | --- |
| GET | `/api/health` | Saúde da API |
| GET | `/api/empresas?busca=aurora` | Pesquisar empresas |
| GET | `/api/empresas/{id}` | Obter perfil |
| **POST** | **`/api/empresas/{id}/verificacoes`** | **Verificar e registrar automaticamente** |
| GET | `/api/empresas/{id}/arvore` | Árvore societária |
| GET | `/api/empresas/{id}/relatorio` | Relatório consolidado em JSON |
| GET | `/api/consultas` | Histórico de consultas |
| POST | `/api/consultas` | Registro manual disponível para testes/compatibilidade, não usado pela interface |
| GET/POST | `/api/empresas/{id}/integracoes` | Rotas técnicas para testes de fornecedores, não visíveis na interface |

A operação de verificação é um POST explícito e não ocorre simplesmente ao ler dados. A aplicação inicia esse POST ao selecionar a empresa; sua execução fica documentada em `ConsultationRepository` e `BureauIntegrationService`.

## Testes

```bash
docker compose up -d --build
docker compose --profile tests run --rm tests-php
docker compose --profile tests run --rm tests-rest
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

Sem Docker, os testes PHP e as verificações estáticas podem ser executados com:

```bash
php tests/test_bureau_contract.php
php tests/test_report_service.php
python3 tests/test_accessibility_static.py
```

Veja [docs/TESTES.md](docs/TESTES.md) e [docs/ACESSIBILIDADE.md](docs/ACESSIBILIDADE.md). Valide a interface em um navegador real, incluindo teclado, zoom e exportação em PDF. Boas práticas de acessibilidade **não equivalem a uma certificação WCAG**.

## Estrutura

```text
api/public/index.php                  Endpoints REST
api/src/BureauIntegrationService.php  Integração dos fornecedores
api/src/ConsultationRepository.php    Histórico de consultas
api/src/ConsolidatedReportService.php Relatório consolidado
frontend/src/App.vue                  Pesquisa, abas e histórico automático
frontend/src/components/ArvoreSocietaria.vue
frontend/src/components/RelatorioConsolidado.vue
infra/mysql/                           Tabelas, dados e migrações
mock-bureaus/router.php                Serviços internos de demonstração
tests/                                 Testes PHP, HTTP e acessibilidade
docs/                                  Arquitetura, acessibilidade e testes
compose.yaml                           Execução local
```
