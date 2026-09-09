---
projeto: Aquapulse
tipo: referencia
tags:
  - aquapulse
  - tipo/geral
---

# Legenda do gráfico

Como ler o mapa da equipe e como reproduzir exatamente as mesmas cores em outro
Obsidian. Voltar para [[equipe]] · índice em [[00-inicio]]

> `.obsidian/` está no `.gitignore` — é preferência local e **não é
> versionada**. Cada integrante precisa aplicar a configuração abaixo no
> próprio Obsidian uma única vez.

---

## Legenda de cores

| Cor | Grupo | Consulta | O que agrupa |
| --- | --- | --- | --- |
| ⬜ `#EAF6FF` branco azulado | Núcleo Aquapulse | `tag:#tipo/hub` | [[00-inicio]] e [[equipe]] |
| 🟦 `#00D9FF` azul-ciano | Frontend | `tag:#papel/frontend` | [[frontend]] + 10 entregas |
| 🟪 `#8B5CF6` roxo | Backend | `tag:#papel/backend` | [[backend]], [[api]] + 10 entregas |
| 🟩 `#22C55E` verde | Banco de Dados | `tag:#papel/banco-de-dados` | [[banco-de-dados]] + 4 entregas |
| 🟧 `#FF9F1C` laranja | Analista de Qualidade | `tag:#papel/qualidade` | [[analista-de-qualidade]] + 6 entregas |
| 🩷 `#FF4ECD` rosa | Analista de Negócios | `tag:#papel/negocios` | [[analista-de-negocios]] + 6 entregas |
| 🟨 `#FFD166` dourado | Granmaster | `tag:#papel/granmaster` | [[granmaster]], [[arquitetura]], [[decisoes]], [[tarefas]] + 6 entregas |
| ⬛ `#94A3B8` cinza | Sem classificação | `tag:#tipo/geral` | [[status-atual]], [[registro-de-entregas]], esta nota |

**Cada nota tem exatamente uma tag de função.** Nenhuma nota casa com dois
grupos, então a ordem da lista não altera o resultado — mas mantenha a ordem
acima para que a legenda do painel fique legível.

---

## Configuração automática (já aplicada neste repositório)

O arquivo `.obsidian/graph.json` deste projeto já vem com os oito grupos
configurados. Uma cópia de segurança do arquivo original está em
`.obsidian/graph.json.bak-aquapulse`.

Se você clonou o repositório, esse arquivo **não veio junto** (está ignorado
pelo Git). Siga a configuração manual abaixo.

---

## Configuração manual, passo a passo

1. Abra o **Gráfico** (ícone de grafo na barra lateral, ou `Ctrl/Cmd + P` →
   "Abrir visualização em gráfico").
2. Clique no ícone de **configurações** no canto superior esquerdo do painel.
3. Abra a seção **Grupos** (Groups) e clique em **Novo grupo** oito vezes.
4. Preencha cada grupo **nesta ordem**, colando a consulta e definindo a cor:

| Ordem | Consulta | Cor (hex) |
| --- | --- | --- |
| 1 | `tag:#tipo/hub` | `#EAF6FF` |
| 2 | `tag:#papel/frontend` | `#00D9FF` |
| 3 | `tag:#papel/backend` | `#8B5CF6` |
| 4 | `tag:#papel/banco-de-dados` | `#22C55E` |
| 5 | `tag:#papel/qualidade` | `#FF9F1C` |
| 6 | `tag:#papel/negocios` | `#FF4ECD` |
| 7 | `tag:#papel/granmaster` | `#FFD166` |
| 8 | `tag:#tipo/geral` | `#94A3B8` |

> Para definir a cor exata, clique no círculo colorido do grupo e digite o
> código hexadecimal no seletor. Não use o conta-gotas: ele não garante o valor.

---

## Ajustes de exibição aplicados

Seção **Filtros**

| Opção | Valor | Por quê |
| --- | --- | --- |
| Mostrar tags | desligado | as tags viram nós e poluiriam a galáxia |
| Mostrar anexos | desligado | imagens de referência não são notas |
| Mostrar não resolvidos | desligado | esconde links para notas inexistentes |
| Mostrar órfãos | desligado | nenhuma nota deste mapa é órfã; a opção evita ruído futuro |

Seção **Exibição**

| Opção | Valor |
| --- | --- |
| Setas | ligado |
| Esmaecimento do texto | `0.5` — nomes visíveis em mais níveis de zoom |
| Tamanho dos nós | `1.3` — pontos levemente maiores |
| Espessura das linhas | `0.6` — linhas finas, conexões discretas |

Seção **Forças**

| Opção | Valor | Efeito |
| --- | --- | --- |
| Força central | `0.55` | mantém [[00-inicio]] e os núcleos ao centro |
| Força de repulsão | `13` | separa os grupos e evita nós sobrepostos |
| Força das ligações | `1` | mantém cada entrega presa à sua função |
| Distância das ligações | `220` | distância média, sem esticar demais |

Nenhuma posição de nó foi fixada — o arranjo é calculado pelo Obsidian a cada
abertura. `workspace.json` não foi alterado.

---

## Tema visual (opcional)

O snippet `.obsidian/snippets/aquapulse-galaxy.css` aplica o fundo azul-marinho
quase preto, o gradiente radial e as cores de linha e de nó neutro.

**Como ativar:**

1. **Configurações → Aparência → Modo base → Escuro**
   (o snippet só age sobre `.theme-dark`).
2. Na mesma tela, role até **Trechos de CSS** (CSS snippets).
3. Clique no ícone de **recarregar** para o Obsidian encontrar o arquivo.
4. Ligue a chave **aquapulse-galaxy**.

O snippet afeta **somente o Obsidian**. O CSS do site Aquapulse vive em
`assets/css/` e não é tocado.

**Limitação conhecida:** dependendo da versão do Obsidian, a `canvas` do
gráfico pinta o próprio fundo a partir de `--background-secondary`. Quando isso
acontece, o gradiente radial não aparece dentro do painel do gráfico — o fundo
fica azul-marinho plano, na mesma cor. É degradação prevista: nada quebra, e as
cores dos nós continuam corretas, porque vêm dos grupos do `graph.json`, não do
snippet.

---

## Como o mapa está organizado

```
                        [[00-inicio]]          núcleo central (branco azulado)
                             │
                        [[equipe]]             núcleo de organização
                             │
        ┌────────┬───────────┼───────────┬──────────┬──────────┐
   [[frontend]] [[backend]] [[banco-de-dados]]      │          │
        │            │            │      [[analista-de-qualidade]]
     10 entregas  10 entregas  4 entregas    [[analista-de-negocios]]
                                                 [[granmaster]]
                                              6 entregas cada
```

Cada entrega tem link de volta para a sua função, então o Obsidian desenha um
aglomerado por cor. As ligações entre funções existem apenas onde há integração
real — por exemplo [[frontend]] ↔ [[api]] ↔ [[backend]] ↔ [[banco-de-dados]].

---

## Manutenção

Ao criar uma nota nova:

1. Copie o bloco de propriedades de uma entrega existente.
2. Ajuste `papel`, `status` e a tag `papel/*` — **uma só tag de função por
   nota**, senão o nó pode receber a cor de outro grupo.
3. Registre-a em [[registro-de-entregas]].
4. Adicione o link a partir do núcleo da função e o link de volta na entrega.

## Relacionadas

[[equipe]] · [[registro-de-entregas]] · [[00-inicio]] · [[decisoes]]
