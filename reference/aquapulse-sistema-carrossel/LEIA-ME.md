# Aquapulse — seção do sistema e carrossel

Este pacote contém a nova referência visual da seção do sistema, cinco capturas preparadas e uma demonstração em vídeo. Não altera os arquivos do site.

## Arquivos

- `referencia-secao-sistema.png`: nova composição, fundo branco, textos à esquerda e a captura real do sistema à direita.
- `previa-carrossel.mp4`: demonstração de 15 segundos, mostrando as cinco telas e a transição suave. É uma prévia; não precisa ser usada como vídeo no site.
- `slides/`: cinco imagens PNG em 3840 × 1920, com ampliação e ajuste leve de nitidez.
- `slides-web/`: cinco versões WebP com compressão sem perdas em 1920 × 960, mais leves para carregar no carrossel.
- `ORIENTACAO-PARA-CLAUDE.md`: conteúdo exato e orientações para implementar esta seção no projeto existente.

## Ordem das telas

| Ordem | Tela | Arquivo PNG |
| --- | --- | --- |
| 1 | Visão geral | `slides/01-visao-geral.png` |
| 2 | Nível do reservatório | `slides/02-nivel-reservatorio.png` |
| 3 | Relatórios | `slides/03-relatorios.png` |
| 4 | Mapas | `slides/04-mapas.png` |
| 5 | Alertas | `slides/05-alertas.png` |

As versões WebP seguem os mesmos nomes, na pasta `slides-web/`.

## O que foi feito nas capturas

Preservação dos textos, gráficos, números e layout dos prints enviados; remoção de alguns pixels periféricos de captura e do aviso de link do navegador no canto inferior esquerdo do print de Mapas; padronização para 2:1 sem esticar a interface; ampliação com interpolação Lanczos e nitidez leve nos PNGs.

A ampliação não recupera detalhes que não estavam no arquivo original e não equivale a uma nova captura nativa em 4K. As regiões que já estavam cortadas nos prints continuam cortadas: não foram inventadas linhas de tabela ou informações fora do enquadramento. Para máxima definição futura, capture diretamente o sistema em maior resolução.

Os dados e eventuais inconsistências presentes nas telas não foram corrigidos neste trabalho visual. Os créditos do mapa foram preservados.

## Uso no projeto

Extraia a pasta `aquapulse-sistema-carrossel` dentro da pasta de referências que você já utiliza no projeto, normalmente `references/` ou `reference/`.

Depois envie ao Claude Code:

```text
Leia references/aquapulse-sistema-carrossel/ORIENTACAO-PARA-CLAUDE.md e reformule somente a seção de apresentação do sistema conforme referencia-secao-sistema.png. Preserve os textos e o fundo branco. Implemente à direita o carrossel automático das cinco imagens de slides-web/, na ordem indicada, usando previa-carrossel.mp4 como referência da transição. Preserve a hero, a seção de vantagens e as demais partes do projeto.
```

Se a pasta se chama `reference/`, ajuste o caminho no comando.

Não publicar a referência inteira como uma imagem da seção: os textos externos e os controles devem ser HTML real. Apenas os prints são imagens.
