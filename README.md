# Aquapulse — Landing page e login

- **Etapa 1** — landing page institucional pública (`index.php`).
- **Etapa 2** — tela de login com autenticação PHP funcional (`login.php` + `api/` + `backend/`),
  sustentada por um repositório **simulado**. Sem banco de dados, cadastro,
  recuperação de senha, dashboard ou sistema interno.

Documentação da etapa 2: [`docs/login-stage.md`](docs/login-stage.md) e
[`docs/api-contract.md`](docs/api-contract.md).

## Stack

PHP (apenas para estruturar e servir a página), HTML semântico, CSS e JavaScript
puro. Sem frameworks, bundlers, dependências ou banco de dados.

## Como executar

Com o PHP disponível no PATH, a partir da raiz do projeto:

```bash
php -S localhost:8000
```

Depois acesse <http://localhost:8000>.

Se o PHP não estiver no PATH (instalação via XAMPP no Windows, por exemplo):

```powershell
& C:\xampp\php\php.exe -S localhost:8000 -t .
```

Também funciona ao colocar a pasta em `htdocs/` do Apache/XAMPP.

## Estrutura

```
index.php                       documento principal
includes/
  config.php                    textos, navegação e helpers de escape
  icons.php                     biblioteca de ícones SVG (traço linear)
  header.php                    cabeçalho + navegação (com menu mobile)
  footer.php                    rodapé
  sections/
    hero.php                    seção 1 — hero
    informacoes.php             seção 2 — por que monitorar represas
    sistema.php                 seção 3 — como o Aquapulse apoia a operação
    vantagens.php               seção 4 — vantagens + chamada final
assets/
  css/style.css                 estilos (variáveis, componentes, responsivo)
  js/main.js                    menu mobile, rolagem, animações discretas
  images/                       imagens públicas otimizadas
login.php                       tela de login (front-end)
api/v1/auth/                    pontos de entrada da API (login, logout, me)
backend/                        regras, sessão e repositório de usuários
docs/                           contrato da API e guia da etapa 2
reference/                      material de referência original (não alterado)
validation/screenshots/         capturas de validação
```

## Observações

- Os botões **Entrar** e **Solicitar demonstração** (bloco final) estão apenas
  preparados visualmente: exibem um aviso de indisponibilidade e não apontam
  para páginas ou back-ends inexistentes.
- O painel exibido na seção "sistema" é uma **prévia ilustrativa em imagem**,
  não um dashboard funcional.

## Evidências de validação

A pasta `validation/screenshots/` guarda as capturas usadas na revisão visual
(geradas com Chrome headless sobre o servidor local):

| Arquivo | Conteúdo |
| --- | --- |
| `desktop-1920-full.png` | página completa em 1920px (1920×4296) |
| `hero-1920-centered.png` | hero em 1920px, com o container ampliado |
| `desktop-1440-full.png` | página completa em 1440px (1440×4293) |
| `mobile-375-full.png` | página completa em 375px (375×8015) |
| `hero-1440.png` | hero em 1440px |
| `informacoes-1440.png` | seção de informações em 1440px |
| `sistema-1440.png` | seção do sistema em 1440px |
| `vantagens-1440.png` | seção de vantagens em 1440px |
| `hero-mobile-375.png` | hero em 375px |
