<?php
/**
 * Acompanhamento automático de inscrição (ackfup) — plugin para GLPI 10/11
 *
 * Textos e listas de nomes ficam em config.php (fora do Git). Sem ele, vale
 * o config.example.php. Veja o README.
 */

require_once is_file(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php';

function plugin_ackfup_install()   { return true; }
function plugin_ackfup_uninstall() { return true; }

function plugin_ackfup_autor(): int
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    global $DB;
    $nome = trim(ACKFUP_AUTOR);

    foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_users',
              'WHERE' => ['name' => $nome, 'is_deleted' => 0], 'LIMIT' => 1]) as $r) {
        return $cache = (int) $r['id'];
    }
    $p = preg_split('/\s+/', $nome);
    if (count($p) > 1) {
        foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_users',
                  'WHERE' => ['is_deleted' => 0,
                              'firstname' => ['LIKE', $p[0] . '%'],
                              'realname'  => ['LIKE', '%' . end($p)]],
                  'ORDER' => 'id ASC', 'LIMIT' => 1]) as $r) {
            return $cache = (int) $r['id'];
        }
    }
    foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_users',
              'WHERE' => ['is_deleted' => 0, 'firstname' => ['LIKE', $p[0] . '%']],
              'ORDER' => 'id ASC', 'LIMIT' => 1]) as $r) {
        return $cache = (int) $r['id'];
    }
    return $cache = ACKFUP_AUTOR_FALL;
}

function plugin_ackfup_find(int $tid): ?int
{
    global $DB;
    foreach ($DB->request([
        'SELECT' => 'id', 'FROM' => 'glpi_itilfollowups',
        'WHERE'  => ['itemtype' => 'Ticket', 'items_id' => $tid,
                     'users_id' => plugin_ackfup_autor()],
        'ORDER'  => 'id ASC', 'LIMIT' => 1,
    ]) as $r) { return (int) $r['id']; }
    return null;
}

/** Maior data entre documentos e vinculos do chamado. */
function plugin_ackfup_ultima_data(int $tid): ?int
{
    global $DB;
    $max = null;

    foreach ($DB->request([
        'SELECT'     => 'd.date_creation AS dt',
        'FROM'       => 'glpi_documents_items AS di',
        'INNER JOIN' => ['glpi_documents AS d' => ['ON' => ['d' => 'id', 'di' => 'documents_id']]],
        'WHERE'      => ['di.itemtype' => 'Ticket', 'di.items_id' => $tid],
        'ORDER'      => 'd.date_creation DESC', 'LIMIT' => 1,
    ]) as $r) { $max = max($max ?? 0, strtotime($r['dt'])); }

    foreach ($DB->request([
        'SELECT' => 'date_creation AS dt', 'FROM' => 'glpi_documents_items',
        'WHERE'  => ['itemtype' => 'Ticket', 'items_id' => $tid],
        'ORDER'  => 'date_creation DESC', 'LIMIT' => 1,
    ]) as $r) { $max = max($max ?? 0, strtotime($r['dt'])); }

    return $max ?: null;
}

/** Joga o acompanhamento para depois de tudo que ja existe no chamado. */
function plugin_ackfup_reposiciona(int $tid): void
{
    global $DB;
    $fid = plugin_ackfup_find($tid);
    if ($fid === null) { return; }

    $base = plugin_ackfup_ultima_data($tid) ?? time();
    $novo = date('Y-m-d H:i:s', max($base, time()) + 1);

    $DB->update('glpi_itilfollowups',
        ['date' => $novo, 'date_creation' => $novo, 'date_mod' => $novo],
        ['id' => $fid]);
}

function plugin_ackfup_item_add(CommonDBTM $item)
{
    if (!($item instanceof Ticket) || empty($item->fields['id'])) { return $item; }
    $tid = (int) $item->fields['id'];

    if (countElementsInTable('glpi_itilfollowups',
            ['itemtype' => 'Ticket', 'items_id' => $tid]) > 0) {
        return $item;
    }

    $content = plugin_ackfup_texto($tid);
    if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
        // O GLPI 10 grava a entrada do add() sem escapar (espera dados já
        // tratados, como vêm do $_POST). Sem isto, um apóstrofo no texto ou
        // no nome do requerente quebra o INSERT.
        $content = Toolbox::addslashes_deep($content);
    }

    (new ITILFollowup())->add([
        'itemtype'               => 'Ticket',
        'items_id'               => $tid,
        'content'                => $content,
        'is_private'             => 0,
        'users_id'               => plugin_ackfup_autor(),
        '_do_not_compute_status' => true,
        '_disablenotif'          => !ACKFUP_NOTIFICAR,
    ]);

    // os documentos ja foram anexados neste ponto
    plugin_ackfup_reposiciona($tid);

    return $item;
}

/** Documentos anexados depois da abertura: reposiciona de novo. */
function plugin_ackfup_document_added(CommonDBTM $item)
{
    if (($item->fields['itemtype'] ?? '') !== 'Ticket' || empty($item->fields['items_id'])) {
        return $item;
    }
    plugin_ackfup_reposiciona((int) $item->fields['items_id']);
    return $item;
}

/* Saudação ------------------------------------------------------------------
 * Flexionada pelo primeiro nome do requerente, conforme as listas de
 * ACKFUP_NOMES_MASCULINOS / ACKFUP_NOMES_FEMININOS (config.php). Nome fora
 * das listas recebe o neutro "Prezado(a)".
 * -------------------------------------------------------------------------- */

function plugin_ackfup_nomes_masculinos()
{
    return defined('ACKFUP_NOMES_MASCULINOS') ? ACKFUP_NOMES_MASCULINOS : [];
}

function plugin_ackfup_nomes_femininos()
{
    return defined('ACKFUP_NOMES_FEMININOS') ? ACKFUP_NOMES_FEMININOS : [];
}

/** Remove acentos e normaliza para comparacao. */
function plugin_ackfup_chave($nome)
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    if ($t === false) { $t = $nome; }
    return strtoupper(preg_replace('/[^A-Za-z]/', '', $t));
}

/** Primeiro nome do requerente, em caixa de titulo. Vazio se nao houver. */
function plugin_ackfup_primeiro_nome($tid)
{
    global $DB;

    foreach ($DB->request([
        'SELECT' => ['users_id'],
        'FROM'   => 'glpi_tickets_users',
        'WHERE'  => ['tickets_id' => (int) $tid, 'type' => CommonITILActor::REQUESTER],
        'LIMIT'  => 1,
    ]) as $r) {
        $u = new User();
        if (!$u->getFromDB($r['users_id'])) { break; }
        $bruto = trim((string) $u->fields['firstname']);
        if ($bruto === '') { break; }
        $primeiro = explode(' ', preg_replace('/\s+/', ' ', $bruto))[0];
        return mb_convert_case($primeiro, MB_CASE_TITLE, 'UTF-8');
    }
    return '';
}

/** "Prezada Maria," / "Prezado João," / "Prezado(a) Alex," */
function plugin_ackfup_saudacao($tid)
{
    $nome = plugin_ackfup_primeiro_nome($tid);
    if ($nome === '') { return 'Prezado(a) candidato(a),'; }

    $chave = plugin_ackfup_chave($nome);
    if (in_array($chave, plugin_ackfup_nomes_femininos(), true)) { return "Prezada $nome,"; }
    if (in_array($chave, plugin_ackfup_nomes_masculinos(), true)) { return "Prezado $nome,"; }
    return "Prezado(a) $nome,";
}

function plugin_ackfup_texto($tid)
{
    return str_replace('{SAUDACAO}', plugin_ackfup_saudacao($tid), ACKFUP_TEXTO);
}

/** Colunas extras nas listas de busca (Chamados e Formcreator). */
function plugin_ackfup_getAddSearchOptionsNew($itemtype)
{
    $opt = [];

    if ($itemtype === 'Ticket') {
        // join comum: o requerente do chamado
        $requerente = [
            'beforejoin' => [
                'table'      => 'glpi_tickets_users',
                'joinparams' => [
                    'jointype'  => 'child',
                    'condition' => 'AND NEWTABLE.`type` = ' . CommonITILActor::REQUESTER,
                ],
            ],
        ];

        // curso do candidato: gravado no campo "Titulo" do usuario
        $opt[] = [
            'id'            => '9871',
            'table'         => 'glpi_usertitles',
            'field'         => 'name',
            'name'          => 'Curso do candidato',
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => [
                'beforejoin' => [
                    'table'      => 'glpi_users',
                    'joinparams' => $requerente,
                ],
            ],
        ];

        // CPF do candidato: e o proprio login (glpi_users.name).
        // 'field' ficticio + 'computation' para escapar do case "glpi_users.name"
        // do Search::giveItem(), que renderiza o nome formatado em vez do login.
        $opt[] = [
            'id'            => '9872',
            'table'         => 'glpi_users',
            'field'         => 'cpf_login',
            'computation'   => 'TABLE.`name`',
            'name'          => 'CPF do candidato',
            'datatype'      => 'string',
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => $requerente,
        ];
    }

    if ($itemtype === 'PluginFormcreatorIssue') {
        // a tabela de issues aponta para o chamado por itemtype/items_id.
        // este e o mesmo salto usado pelas opcoes nativas 14/15 do Formcreator.
        $chamado = [
            'table'      => 'glpi_tickets',
            'joinparams' => [
                'jointype'          => 'itemtype_item_revert',
                'specific_itemtype' => 'Ticket',
            ],
        ];

        // campus: categoria do chamado
        $opt[] = [
            'id'            => '9881',
            'table'         => 'glpi_itilcategories',
            'field'         => 'completename',
            'name'          => 'Campus do candidato',
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => ['beforejoin' => $chamado],
        ];

        // curso: campo "Titulo" do requerente do chamado
        $opt[] = [
            'id'            => '9882',
            'table'         => 'glpi_usertitles',
            'field'         => 'name',
            'name'          => 'Curso do candidato',
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => [
                'beforejoin' => [
                    'table'      => 'glpi_users',
                    'joinparams' => [
                        'beforejoin' => [
                            'table'      => 'glpi_tickets_users',
                            'joinparams' => [
                                'jointype'   => 'child',
                                'condition'  => 'AND NEWTABLE.`type` = ' . CommonITILActor::REQUESTER,
                                'beforejoin' => $chamado,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    return $opt;
}
