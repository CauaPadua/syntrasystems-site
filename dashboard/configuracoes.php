<?php
/** Aquapulse — Configurações. */
/*
 * Tela organizada em abas (tabs). Só a aba "Empresas e represas" começa visível.
 * Os dados vêm de GET api/v1/settings.php, carregados por assets/js/pages/settings.js,
 * que também faz a troca de abas e os botões demonstrativos.
 * Nada é salvo no servidor: não existe endpoint de gravação nesta etapa.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'settings',
    'title'    => 'Configurações',
    'subtitle' => 'Gerencie empresas, represas e preferências do sistema',
]);

$abas = [                                                                 // abas da tela: id => rótulo e ícone; o id monta "tab-{id}" e "painel-{id}"
    'geral'      => ['label' => 'Geral', 'icon' => 'gear'],
    'empresas'   => ['label' => 'Empresas e represas', 'icon' => 'building'],
    'usuarios'   => ['label' => 'Usuários e permissões', 'icon' => 'user-cog'],
    'limites'    => ['label' => 'Limites e alertas', 'icon' => 'alert-triangle'],
    'notificacoes' => ['label' => 'Notificações', 'icon' => 'bell'],
    'seguranca'  => ['label' => 'Segurança', 'icon' => 'shield-lock'],
];
?>

<article class="aq-card aq-card--flush">
  <div class="aq-tabs" role="tablist" aria-label="Seções de configuração"> <?php /* role="tablist": leitores de tela reconhecem o conjunto como abas */ ?>
    <?php $i = 0; foreach ($abas as $id => $aba): $ativa = $id === 'empresas'; // $ativa: só a aba "empresas" começa selecionada ($i é incrementado mas não é usado) ?>
      <?php /* aria-selected indica a aba ativa; tabindex -1 tira as demais da ordem do Tab (navegação por setas, no settings.js) */ ?>
      <button class="aq-tab" type="button" role="tab" id="tab-<?php aq_e($id); ?>"
              aria-controls="painel-<?php aq_e($id); ?>"
              aria-selected="<?php echo $ativa ? 'true' : 'false'; ?>"
              tabindex="<?php echo $ativa ? '0' : '-1'; ?>">
        <?php aq_the_icon($aba['icon']); ?><span><?php aq_e($aba['label']); ?></span>
      </button>
    <?php $i++; endforeach; ?>
  </div>
</article>

<!-- ======================================= aba: Empresas e represas ====== -->
<div class="aq-tabpanel" id="painel-empresas" role="tabpanel" aria-labelledby="tab-empresas"> <?php /* painel visível por padrão (sem hidden) */ ?>
  <div class="aq-grid aq-grid--3">

    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Empresas cadastradas']); ?>
      <div class="aq-field" style="margin-bottom:12px">
        <label class="aq-visually-hidden" for="busca-empresa">Buscar empresa</label>
        <input class="aq-input" type="search" id="busca-empresa" placeholder="Buscar empresa" style="width:100%"> <?php /* filtra a lista de empresas no navegador */ ?>
      </div>
      <button class="aq-btn aq-btn--primary" type="button" style="width:100%;margin-bottom:14px" data-demo-action> <?php /* data-demo-action: mostra um aviso de função demonstrativa */ ?>
        <?php aq_the_icon('plus'); ?><span>Adicionar empresa</span>
      </button>
      <div data-companies></div> <?php /* lista de empresas; clicar em uma atualiza os cards ao lado */ ?>
    </article>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head([
            'title'   => 'Dados da empresa',
            'actions' => '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-demo-action>'
                         . aq_icon('edit') . '<span>Editar dados</span></button>',
        ]); ?>
        <div style="border:1px solid var(--aq-border);border-radius:11px;padding:16px">
          <div class="aq-grid aq-grid--2">
            <div>
              <p class="aq-card__sub">Nome da empresa</p>
              <strong data-field="company.name">—</strong> <?php /* campos company.* = empresa selecionada na lista */ ?>
            </div>
            <div>
              <p class="aq-card__sub">Identificador</p>
              <strong data-field="company.code">—</strong>
            </div>
          </div>
          <div class="aq-grid aq-grid--2" style="margin-top:16px">
            <div>
              <p class="aq-card__sub">Responsável</p>
              <strong data-field="company.manager">—</strong>
            </div>
            <div>
              <p class="aq-card__sub">Status</p>
              <span data-field="company.status"></span> <?php /* badge com status_label */ ?>
            </div>
          </div>
        </div>
      </article>

      <article class="aq-card">
        <?php echo aq_card_head([
            'title'   => 'Represas vinculadas',
            'actions' => '<button class="aq-btn aq-btn--primary aq-btn--sm" type="button" data-demo-action>'
                         . aq_icon('plus') . '<span>Adicionar represa</span></button>',
        ]); ?>
        <div data-company-reservoirs></div> <?php /* represas da empresa selecionada (companies[].reservoirs) */ ?>
      </article>
    </div>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Preferências de monitoramento']); ?>
        <?php
        // Seletores de preferência: alterar um deles só vale na sessão do navegador.
        echo aq_select(['id' => 'pref-nivel', 'label' => 'Unidade de nível', 'options' => ['m' => 'metros (m)', 'cm' => 'centímetros (cm)']]);
        echo aq_select(['id' => 'pref-volume', 'label' => 'Volume', 'options' => ['hm3' => 'hm³', 'm3' => 'm³']]);
        echo aq_select(['id' => 'pref-vazao', 'label' => 'Vazão', 'options' => ['m3s' => 'm³/s', 'ls' => 'L/s']]);
        echo aq_select(['id' => 'pref-refresh', 'label' => 'Atualização automática', 'options' => [
            '1min' => 'A cada 1 minuto', '5min' => 'A cada 5 minutos', '15min' => 'A cada 15 minutos',
        ]]);
        ?>
        <div class="aq-form-row" style="margin-top:14px">
          <span id="rotulo-ativada">Ativada</span>
          <?php /* interruptor acessível: role="switch" + aria-checked (true/false) alternado pelo JS */ ?>
          <button class="aq-switch" type="button" role="switch" aria-checked="true"
                  aria-labelledby="rotulo-ativada" data-switch="auto-refresh"></button>
        </div>
      </article>

      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Indicadores configurados']); ?>
        <div data-indicators></div> <?php /* settings.indicators com seus interruptores */ ?>
      </article>
    </div>
  </div>

  <article class="aq-card" style="margin-top:var(--aq-content-gap)">
    <?php echo aq_card_head([
        'title'   => 'Limites principais',
        'actions' => '<button class="aq-btn aq-btn--outline aq-btn--sm" type="button" data-demo-action>'
                     . aq_icon('gear') . '<span>Configurar limites</span></button>',
    ]); ?>
    <div class="aq-grid aq-grid--4" data-thresholds></div> <?php /* settings.thresholds (nível de atenção, faixa de pH, chuva crítica) */ ?>
  </article>
</div>

<!-- ================================== demais abas (mesma fonte de dados) == -->
<div class="aq-tabpanel" id="painel-geral" role="tabpanel" aria-labelledby="tab-geral" hidden> <?php /* abas ocultas até serem selecionadas */ ?>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Preferências gerais do sistema']); ?>
    <div class="aq-grid aq-grid--3">
      <?php
      echo aq_select(['id' => 'geral-idioma', 'label' => 'Idioma', 'options' => ['pt-BR' => 'Português (Brasil)']]);
      echo aq_select(['id' => 'geral-fuso', 'label' => 'Fuso horário', 'options' => ['sp' => 'America/Sao_Paulo (UTC−3)']]); // mesmo fuso usado por Support\Clock
      echo aq_select(['id' => 'geral-tela', 'label' => 'Tela inicial', 'options' => ['overview' => 'Visão geral', 'alerts' => 'Alertas', 'maps' => 'Mapas']]);
      ?>
    </div>
  </article>
</div>

<div class="aq-tabpanel" id="painel-usuarios" role="tabpanel" aria-labelledby="tab-usuarios" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Usuários e permissões']); ?>
    <?php echo aq_table_open('Usuários do sistema'); ?>
    <table class="aq-table">
      <thead><tr><th scope="col">Usuário</th><th scope="col">E-mail</th><th scope="col">Perfil</th><th scope="col">Status</th></tr></thead>
      <tbody data-users></tbody> <?php /* o JS mostra só o usuário logado (e-mail lido de data-user-email na topbar) */ ?>
    </table>
    <?php echo aq_table_close(); ?>
    <div class="aq-demo-note" style="margin-top:14px">
      <?php aq_the_icon('info'); ?>
      <span>O cadastro de usuários depende do banco de dados. Nesta etapa aparece apenas o usuário
      demonstrativo da sessão atual.</span>
    </div>
  </article>
</div>

<div class="aq-tabpanel" id="painel-limites" role="tabpanel" aria-labelledby="tab-limites" hidden> <?php /* id "painel-limites": destino do botão "Configurar alertas" da tela de alertas */ ?>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Limites e alertas']); ?>
    <div data-limits-form></div> <?php /* campos dos limites gerados pelo JS (somente leitura) */ ?>
  </article>
</div>

<div class="aq-tabpanel" id="painel-notificacoes" role="tabpanel" aria-labelledby="tab-notificacoes" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Canais de notificação']); ?>
    <div data-notifications></div> <?php /* settings.notifications */ ?>
  </article>
</div>

<div class="aq-tabpanel" id="painel-seguranca" role="tabpanel" aria-labelledby="tab-seguranca" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Segurança da sessão']); ?>
    <?php /* textos informativos que descrevem a configuração real de backend/config/session.php */ ?>
    <div class="aq-form-row"><span>Sessão protegida por cookie <code>HttpOnly</code></span><?php echo aq_badge('Ativo', 'normal'); ?></div>
    <div class="aq-form-row"><span><code>SameSite=Lax</code> contra requisições cross-site</span><?php echo aq_badge('Ativo', 'normal'); ?></div>
    <div class="aq-form-row"><span>Inatividade máxima da sessão</span><strong>30 minutos</strong></div> <?php /* idle_timeout = 1800 s */ ?>
    <div class="aq-form-row"><span>Duração máxima absoluta</span><strong>12 horas</strong></div> <?php /* absolute_timeout = 43200 s */ ?>
    <div class="aq-demo-note" style="margin-top:14px">
      <?php aq_the_icon('info'); ?>
      <span>Autenticação de dois fatores e registro de auditoria dependem do banco de dados.</span>
    </div>
  </article>
</div>

<!-- ------------------------------------------------------ barra de ações -->
<div style="display:flex;justify-content:flex-end;gap:12px">
  <button class="aq-btn aq-btn--ghost" type="button" data-cancel>Cancelar</button> <?php /* desfaz as alterações da sessão (settings.js) */ ?>
  <button class="aq-btn aq-btn--primary" type="button" data-save> <?php /* "salva" apenas no navegador e mostra um aviso (toast) */ ?>
    <?php aq_the_icon('save'); ?><span>Salvar alterações</span>
  </button>
</div>

<div class="aq-demo-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Persistência demonstrativa.</strong> As preferências alteradas aqui valem apenas para a
  sessão atual do navegador. A gravação definitiva será implementada com o banco de dados.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/settings.js']]); // fecha o layout e carrega a lógica das abas e dos dados