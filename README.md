# Acompanhamento automático de inscrição (ackfup) — Plugin para GLPI 10 e 11

Responde automaticamente a cada **novo chamado** com um acompanhamento de "recebemos sua documentação". O acompanhamento é publicado em nome de um usuário avaliador, com **saudação personalizada** pelo primeiro nome do requerente. Foi feito para processos seletivos em que o candidato envia documentos por chamado.

| | |
|---|---|
| **Versão** | 1.3.1 |
| **GLPI** | 10.0.x e 11.0.x |
| **Opcional** | Formcreator (colunas extras na lista de solicitações) |
| **Licença** | GPLv2+ |

---

## O que ele faz

### 1. Acompanhamento automático
Quando um chamado é criado **sem nenhum acompanhamento**, o plugin publica um acompanhamento **público** com o texto de `ACKFUP_TEXTO`:

> Prezada Maria,
> Sua documentação foi **recebida** e está em análise. […]

- O autor é o usuário de `ACKFUP_AUTOR`. Se ele não for encontrado, vale o ID de `ACKFUP_AUTOR_FALL`.
- O status do chamado **não muda**.
- Notificações por e-mail só são enviadas com `ACKFUP_NOTIFICAR = true`.

### 2. Sempre a última mensagem
O plugin ajusta a data do acompanhamento para **1 segundo depois** do último documento anexado. Isso vale também para documentos anexados depois da abertura, e mantém a confirmação no fim da linha do tempo do chamado.

### 3. Saudação por gênero
O primeiro nome do requerente (campo *Nome* do usuário) é comparado, sem acentos, com as listas `ACKFUP_NOMES_FEMININOS` e `ACKFUP_NOMES_MASCULINOS`:

| Nome | Saudação |
|---|---|
| Na lista feminina | *Prezada Maria,* |
| Na lista masculina | *Prezado João,* |
| Fora das listas | *Prezado(a) Alex,* |
| Sem requerente ou sem nome | *Prezado(a) candidato(a),* |

### 4. Colunas extras nas buscas
Pensadas para seletivos em que o **login do candidato é o CPF**, o **curso** fica no campo *Título* do usuário e o **campus** é a categoria do chamado:

| Lista | Coluna | Origem |
|---|---|---|
| Chamados | Curso do candidato | *Título* do requerente (`glpi_usertitles`) |
| Chamados | CPF do candidato | Login do requerente (`glpi_users.name`) |
| Formcreator → Solicitações | Campus do candidato | Categoria do chamado |
| Formcreator → Solicitações | Curso do candidato | *Título* do requerente |

## Instalação

```bash
cd /var/www/html/glpi/plugins
git clone https://github.com/andersonthales/ackfup.git
cd ackfup
cp config.example.php config.php    # ajuste textos, autor e listas de nomes
chown -R www-data:www-data .
```

Em **Configurar → Plugins**, clique em **Instalar** e depois em **Ativar**. O plugin não cria tabelas.

## Configuração

Toda a configuração fica em **`config.php`**, que **não é versionado** porque costuma ter textos institucionais e nomes reais. Sem ele, o plugin usa o `config.example.php`.

| Constante | Uso |
|---|---|
| `ACKFUP_AUTOR` | Login ou "Nome Sobrenome" do usuário avaliador |
| `ACKFUP_AUTOR_FALL` | ID do usuário usado se o avaliador não for encontrado (padrão: 2, o `glpi`) |
| `ACKFUP_TEXTO` | HTML do acompanhamento; `{SAUDACAO}` é substituído pela saudação |
| `ACKFUP_NOTIFICAR` | `true` para disparar as notificações do GLPI |
| `ACKFUP_NOMES_MASCULINOS` / `ACKFUP_NOMES_FEMININOS` | Primeiros nomes em MAIÚSCULAS, sem acento |

A cada novo seletivo, normalmente basta editar `ACKFUP_TEXTO` (datas, nome do processo). Não é preciso reinstalar o plugin.

### Montando as listas de nomes

Uma forma prática é extrair os primeiros nomes dos candidatos do banco do GLPI:

```sql
SELECT DISTINCT UPPER(SUBSTRING_INDEX(TRIM(firstname), ' ', 1)) AS nome
FROM glpi_users
WHERE is_deleted = 0 AND firstname <> ''
ORDER BY nome;
```

Depois, separe os nomes manualmente em masculinos e femininos. Os que deixarem dúvida podem ficar de fora, e recebem *Prezado(a)*.

## Limitações conhecidas

- O acompanhamento só é criado se o chamado **não tiver nenhum** acompanhamento no momento da criação.
- As colunas de busca seguem a convenção CPF = login, curso = *Título* e campus = categoria. Em outros cenários, elas mostram dados sem sentido.

## Estrutura

```
ackfup/
├── setup.php             # Registro do plugin e hooks (item_add de Ticket e Document_Item)
├── hook.php              # Lógica: acompanhamento, reposicionamento, saudação, colunas de busca
├── config.example.php    # Modelo de configuração
└── config.php            # Sua configuração (não versionado)
```

## Changelog

Veja [CHANGELOG.md](CHANGELOG.md).

## Licença

[GPLv2 ou posterior](LICENSE).

## Autor

**Anderson Thales** — [@andersonthales](https://github.com/andersonthales)
