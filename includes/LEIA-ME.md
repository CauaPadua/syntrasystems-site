# includes/ — os pedaços da página pública (FRONT-END)

Estes arquivos **não são páginas**. São pedaços de HTML que o `index.php` junta
para formar a home.

## Por que dividir a página em pedaços

Se tudo estivesse dentro de `index.php`, o arquivo teria centenas de linhas e
achar qualquer coisa seria difícil. Dividido assim, cada arquivo tem **um
assunto só**, e o `index.php` fica curto e legível:

```php
<?php require __DIR__ . '/includes/header.php'; ?>          <!-- cabeçalho -->
<?php require __DIR__ . '/includes/sections/hero.php'; ?>   <!-- capa -->
<?php require __DIR__ . '/includes/sections/informacoes.php'; ?>
...
<?php require __DIR__ . '/includes/footer.php'; ?>          <!-- rodapé -->
```

O comando `require` do PHP significa "cole o conteúdo deste arquivo aqui".

---

## Os arquivos

| Arquivo | É |
|---|---|
| `header.php` | Cabeçalho com logotipo e menu. Aparece em cima de tudo. |
| `footer.php` | Rodapé. |
| `config.php` | **Os textos do site.** Menu e conteúdo dos cartões ficam aqui, não no HTML. |
| `icons.php` | Todos os ícones, desenhados em SVG. |

### Seções da home, na ordem em que aparecem

| Arquivo | Seção |
|---|---|
| `sections/hero.php` | A capa, com a fotografia grande e o título |
| `sections/informacoes.php` | "Por que monitorar", com o carrossel de fotos |
| `sections/importancia.php` | Os quatro cartões de importância |
| `sections/sistema.php` | Como o sistema apoia a operação |
| `sections/vantagens.php` | Vantagens e chamada final |

---

## Dois detalhes que valem explicar na apresentação

### Os textos ficam separados do HTML

Em `config.php` existem listas como `AQ_NAV` (itens do menu) e `AQ_INFO_CARDS`
(os quatro cartões). As seções percorrem essas listas com `foreach`.

Assim, para mudar um texto não é preciso mexer na estrutura da página — e para
mudar a estrutura não é preciso reescrever os textos.

### Toda saída de texto passa por `aq_out()`

```php
<?php aq_out($card['title']); ?>
```

Essa função **escapa** o texto antes de imprimir, convertendo caracteres como
`<` e `>` em código seguro. É a proteção contra **XSS**, um ataque em que
alguém injeta HTML ou JavaScript malicioso através de um texto.

É uma boa pergunta de professor: "como você trata a saída de dados?" — e a
resposta está aqui.
