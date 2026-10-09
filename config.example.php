<?php
/**
 * Configuração do ackfup — EXEMPLO.
 *
 * Copie este arquivo para config.php e ajuste. O config.php não é versionado
 * (.gitignore) e, quando existe, é usado no lugar deste.
 */

// Usuário em nome de quem o acompanhamento é publicado: login (glpi_users.name)
// ou "Nome Sobrenome". Se não for encontrado, usa ACKFUP_AUTOR_FALL (ID do usuário).
const ACKFUP_AUTOR      = 'Avaliador';
const ACKFUP_AUTOR_FALL = 2;

// Texto do acompanhamento, em HTML. {SAUDACAO} vira "Prezada Maria,",
// "Prezado João," ou "Prezado(a) Alex,", conforme as listas abaixo.
const ACKFUP_TEXTO = '<p>{SAUDACAO}</p>'
    . '<p>Sua documentação foi <strong>recebida</strong> e está em análise.<br>'
    . 'O resultado será divulgado a partir de <strong>DD/MM/AAAA</strong>.</p>'
    . '<p>Atenciosamente,<br>Coordenação do Processo Seletivo</p>';

// true = envia as notificações do GLPI ao criar o acompanhamento.
const ACKFUP_NOTIFICAR = false;

// Primeiros nomes, em MAIÚSCULAS e sem acento, para flexionar a saudação.
// Nome fora das duas listas recebe "Prezado(a)".
const ACKFUP_NOMES_MASCULINOS = ['ANTONIO', 'CARLOS', 'FRANCISCO', 'JOAO', 'JOSE', 'LUCAS', 'PEDRO'];
const ACKFUP_NOMES_FEMININOS  = ['ANA', 'BEATRIZ', 'FRANCISCA', 'JULIANA', 'MARIA', 'MARIANA'];
