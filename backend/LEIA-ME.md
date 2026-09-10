# backend/ — o cérebro do sistema (BACK-END)

Roda **no servidor**, em PHP. Nada aqui desenha tela. Este código só decide
dados e regras, e devolve o resultado para quem pediu.

Quem chama estes arquivos são os endpoints em `api/v1/`. A página pública
(`index.php`) e o login **não** carregam esta pasta diretamente.

---

## As pastas, de fora para dentro

```
backend/
├── bootstrap.php      Liga o back-end: carrega as classes e prepara a sessão.
├── config/            Configuração da sessão (validade, segurança do cookie).
├── storage/mock/      Os dados simulados. Hoje fazem o papel do banco.
└── src/               O código de verdade, dividido por responsabilidade.
```

## Dentro de `src/`, quem faz o quê

| Pasta | Papel | Analogia |
|---|---|---|
| `Contracts/` | Interfaces: o **contrato** que um fornecedor de dados precisa cumprir. | O pedido do cliente |
| `Repositories/` | Quem **busca** os dados. Hoje lê do mock; amanhã, do banco. | O estoque |
| `Services/` | Quem aplica as **regras de negócio** sobre esses dados. | A cozinha |
| `Http/` | Ferramentas para ler o pedido e montar a resposta HTTP. | O balcão |
| `Auth/` | Regras de login: confere e-mail e senha. | A portaria |
| `Support/` | Utilidades usadas por todos: container, validação, relógio, guarda. | A caixa de ferramentas |

---

## Os arquivos que mais importam na apresentação

### `src/Support/Container.php`
**O ponto único de troca do banco de dados.** Hoje ele devolve o repositório
simulado. Para ligar um banco real, muda-se só o corpo de um método aqui — o
resto do sistema não percebe.

### `src/Contracts/MonitoringRepositoryInterface.php`
O contrato. Diz **quais** métodos existem para buscar dados, sem dizer **de
onde** vêm. É o que permite trocar o mock pelo banco.

### `src/Services/StatusRules.php`
Uma regra de negócio concreta e fácil de explicar: decide se uma represa está
em situação **normal, atenção ou crítica** conforme o nível medido.

### `src/Auth/AuthService.php`
Confere as credenciais do login. Não imprime nada: só responde "confere" ou
"não confere". Quem transforma isso em resposta HTTP é o endpoint.

### `src/Support/Guard.php`
O porteiro. Verifica se existe sessão válida antes de qualquer endpoint
entregar dados.

---

## Por que tantas camadas?

Porque cada uma tem **um motivo para mudar**:

- Mudou a fonte dos dados? Mexe só em `Repositories/`.
- Mudou a regra de quando uma represa é crítica? Mexe só em `Services/`.
- Mudou o formato da resposta? Mexe só em `Http/`.

Separar assim é o que torna o sistema fácil de manter. É o mesmo princípio de
uma cozinha profissional: quem recebe o pedido não é quem cozinha.
