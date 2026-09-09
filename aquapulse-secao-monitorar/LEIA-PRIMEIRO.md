# Aquapulse — seção Por que monitorar

Este pacote prepara a implementação com Claude Code. Ele não modifica automaticamente seu site.

## Como usar

1. Extraia a pasta `aquapulse-secao-monitorar` dentro da pasta `reference` ou `references` que já existe no projeto. Não crie uma segunda pasta de referências se já houver uma.
2. Abra o projeto no VS Code e envie ao Claude o texto abaixo, corrigindo apenas o nome da pasta caso necessário.

```text
Leia integralmente reference/aquapulse-secao-monitorar/PROMPT-CLAUDE-SECAO-MONITORAR.md e o manifesto-slides.json do mesmo pacote. Abra a imagem de referência e as cinco fotografias antes de implementar. Substitua somente a seção informativa imediatamente abaixo da hero pelo layout fornecido, com quatro slides automáticos no painel grande e a foto lateral fixa. Preserve a hero aprovada, login, dashboard e backend. Execute os testes solicitados e entregue o relatório em português. Não faça commit, push nem deploy.
```

## Conteúdo

- `referencia/secao-monitorar.png`: captura de layout; não é imagem para colocar como seção inteira no site.
- `imagens/01-monitoramento.webp`: primeiro slide, cena do profissional reconstruída a partir da referência.
- `imagens/02-vista-aerea.webp`: segundo slide, vista aérea do reservatório.
- `imagens/03-barragem.webp`: terceiro slide, barragem e vazão controlada.
- `imagens/04-reservatorio.webp`: quarto slide, reservatório e estação de monitoramento.
- `imagens/agua-lateral.webp`: foto estreita fixa; não é um quinto slide.
- `originais/`: cinco PNGs de maior qualidade para futuras edições.
- `manifesto-slides.json`: ordem, descrições alternativas, duração e caminhos relativos dos recursos.
- `PROMPT-CLAUDE-SECAO-MONITORAR.md`: instruções completas de implementação e validação.
- `PROVENIENCIA-IMAGENS.md`: origem e instruções de geração de cada imagem.

As cinco fotografias são ilustrações geradas por IA. Não representam instalações, clientes, colaboradores ou medições reais do Aquapulse. A primeira cena e a lateral foram reconstruídas visualmente: não são recuperações pixel a pixel dos arquivos originais. As demais cenas são fictícias e seguem o tratamento visual da referência.

As versões WebP são para o site; os PNGs e a captura ficam apenas como referências. Não publicar o pacote inteiro na hospedagem: copiar somente os recursos necessários para `assets/images/monitorar/` durante a integração.

Os cinco WebP somam 887.172 bytes (aproximadamente 0,89 MB), com arquivos individuais entre 108 e 240 KB. Os quatro slides têm 1774 × 887 pixels (2:1); a imagem lateral tem 1122 × 1402 pixels. Esses pesos são dos arquivos, não uma medição do tempo de carregamento da página.

## Escopo visual

Quatro slides no painel grande; painel estreito fixo. Esta é uma simplificação explícita da ideia anterior de dois carrosséis sincronizados: preserva a referência e limita a quantidade de imagens. Os dois painéis continuam visíveis no desktop; no celular ficam empilhados.

## O que falta após baixar

O Claude ainda precisa integrar HTML/PHP, CSS e JavaScript ao seu repositório e testar no XAMPP. Este pacote não contém uma substituição pronta de `index.php`, `style.css` ou `main.js`, pois sobrescrever esses arquivos sem inspecionar o projeto poderia desfazer a hero aprovada.
