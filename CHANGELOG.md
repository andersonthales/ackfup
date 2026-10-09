# Changelog

## [1.3.1] - 2026-10-08
### Corrigido
- GLPI 10: apóstrofo no texto ou no nome do requerente fazia a criação do acompanhamento falhar

## [1.3.0] - 2026-10-08
Unifica as duas versões que estavam em produção e prepara o código para ser público.
### Alterado
- Textos, autor e listas de nomes saíram do `hook.php` e foram para `config.php` (não versionado), com modelo em `config.example.php`
- Junta a mensagem com saudação da 1.1.0 e a compatibilidade com GLPI 11 da 1.2.0

## [1.2.0] - 2026-08-26
- Compatibilidade declarada com GLPI 11 (`max` 11.1.99)
- Mensagem curta, sem saudação

## [1.1.0] - 2026-09-02
- Saudação flexionada por gênero a partir do primeiro nome do requerente
- Mensagem em HTML, com data do resultado e assinatura
- Colunas de busca: Curso e CPF do candidato (Chamados); Campus e Curso (Formcreator)
- Reposiciona o acompanhamento depois dos documentos anexados

> A 1.1.0 recebeu as últimas alterações depois da 1.2.0, por isso as datas estão fora de ordem.
