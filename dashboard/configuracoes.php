<?php
/** Aquapulse — Configurações. */
/*
 * Empresas e represas, limites operacionais em vigor, contas de acesso e regras
 * da sessão. Dados de GET api/v1/settings.php, desenhados por
 * assets/js/pages/settings.js.
 *
 * Só o administrador vê as contas e os botões de cadastro (empresa, represa,
 * limites e conta); a API confere o cargo de novo a cada gravação.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

use Aquapulse\Support\Guard;

aq_page_start([
    'route'    => 'settings',
    'title'    => 'Configurações',
    'subtitle' => 'Empresas, represas, limites e contas de acesso',
]);

$podeCadastrar = Guard::can($AQ_USER, 'registry.edit');

/** Botão de ação no cabeçalho de um card (só para quem pode cadastrar). */
$botao = static fn (string $atributo, string $icone, string $texto): string => !$podeCadastrar ? '' :
    '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" ' . $atributo . '>' . aq_icon($icone) . '<span>' . $texto . '</span></button>';

$abas = [
    'empresas'  => ['label' => 'Empresas e represas', 'icon' => 'building'],
    'limites'   => ['label' => 'Limites operacionais', 'icon' => 'sliders'],
    'usuarios'  => ['label' => 'Usuários', 'icon' => 'user-cog'],
    'seguranca' => ['label' => 'Sessão', 'icon' => 'shield-lock'],
];

if (!$podeCadastrar) {
    unset($abas['usuarios']);                                             // a lista de contas é só para o administrador (a API também não a entrega)
}

$sessao = aq_session_config();                                            // mesmos valores aplicados em backend/bootstrap.php
?>

<article class="aq-card aq-card--flush">
  <div class="aq-tabs" role="tablist" aria-label="Seções de configuração">
    <?php foreach ($abas as $id => $aba): $ativa = $id === 'empresas'; ?>
      <button class="aq-tab" type="button" role="tab" id="tab-<?php aq_e($id); ?>"
              aria-controls="painel-<?php aq_e($id); ?>"
              aria-selected="<?php echo $ativa ? 'true' : 'false'; ?>"
              tabindex="<?php echo $ativa ? '0' : '-1'; ?>">
        <?php aq_the_icon($aba['icon']); ?><span><?php aq_e($aba['label']); ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</article>

<!-- ======================================= aba: Empresas e represas ====== -->
<div class="aq-tabpanel" id="painel-empresas" role="tabpanel" aria-labelledby="tab-empresas">
  <div class="aq-grid aq-grid--1-2">
    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Empresas', 'tip' => 'Empresas que a sua conta pode consultar.',
                               'actions' => $botao('data-new-company', 'plus', 'Nova empresa')]); ?>
      <div class="aq-field" style="margin-bottom:12px">
        <label class="aq-visually-hidden" for="busca-empresa">Buscar empresa</label>
        <input class="aq-input" type="search" id="busca-empresa" placeholder="Buscar empresa" style="width:100%">
      </div>
      <div data-companies></div> <?php /* clicar em uma empresa mostra os dados dela ao lado */ ?>
    </article>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Dados da empresa', 'actions' => $botao('data-edit-company', 'edit', 'Editar')]); ?>
        <div class="aq-grid aq-grid--4">
          <div><p class="aq-card__sub">Nome</p><strong data-field="company.name">—</strong></div>
          <div><p class="aq-card__sub">Código</p><strong data-field="company.code">—</strong></div>
          <div><p class="aq-card__sub">Responsável</p><strong data-field="company.manager">—</strong></div>
          <div><p class="aq-card__sub">Situação</p><span data-field="company.status"></span></div>
        </div>
      </article>

      <article class="aq-card">
        <?php echo aq_card_head([
            'title'   => 'Represas da empresa',
            'actions' => $botao('data-new-reservoir', 'plus', 'Nova represa')
                       . '<a class="aq-card__link" href="equipamentos.php">Equipamentos ' . aq_icon('arrow-right') . '</a>',
        ]); ?>
        <div data-company-reservoirs></div>
      </article>
    </div>
  </div>
</div>

<!-- ================================================= aba: Limites ======== -->
<div class="aq-tabpanel" id="painel-limites" role="tabpanel" aria-labelledby="tab-limites" hidden>
  <div class="aq-grid aq-grid--2-1">
    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Nível — limites de cada represa',
                               'tip' => 'Calculados das cotas do cadastro: a de alerta abre Atenção e a de vertimento abre Crítico.']); ?>
      <?php echo aq_table_open('Limites de nível de cada represa'); ?>
      <table class="aq-table">
        <thead>
          <tr><th scope="col">Represa</th><th scope="col">Atenção a partir de</th><th scope="col">Crítico a partir de</th><?php if ($podeCadastrar): ?><th scope="col"><span class="aq-visually-hidden">Editar</span></th><?php endif; ?></tr>
        </thead>
        <tbody data-level-limits></tbody>
      </table>
      <?php echo aq_table_close(); ?>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'pH e chuva — todas as represas', 'tip' => 'Valores lidos da tabela operational_limits.',
                               'actions' => $botao('data-edit-limits', 'edit', 'Alterar')]); ?>
      <div data-limits></div>
    </article>
  </div>

  <div class="aq-note" style="margin-top:14px">
    <?php aq_the_icon('info'); ?>
    <span>Estes limites classificam os valores em todas as telas (Normal, Atenção, Crítico) e decidem quando um
    alerta é aberto. Uma mudança vale a partir da próxima leitura; os alertas já abertos continuam abertos.</span>
  </div>
</div>

<!-- ================================================= aba: Usuários ======= -->
<?php if (isset($abas['usuarios'])): ?>
<div class="aq-tabpanel" id="painel-usuarios" role="tabpanel" aria-labelledby="tab-usuarios" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Contas de acesso', 'tip' => 'Contas cadastradas na tabela users.',
                             'actions' => $botao('data-new-account', 'plus', 'Nova conta')]); ?>
    <?php echo aq_table_open('Contas de acesso'); ?>
    <table class="aq-table">
      <thead>
        <tr><th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Cargo</th><th scope="col">Represas</th><th scope="col">Situação</th><th scope="col">Último acesso</th><th scope="col"><span class="aq-visually-hidden">Editar</span></th></tr>
      </thead>
      <tbody data-users></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <div class="aq-note" style="margin-top:14px">
      <?php aq_the_icon('info'); ?>
      <span><strong>Administrador</strong> vê todas as represas, responde as solicitações e faz todos os cadastros.
      <strong>Gestor da represa</strong> vê só as represas dele, cadastra os equipamentos delas e gera relatórios.
      <strong>Operador</strong> vê só as represas dele, acompanha os dados, trata alertas e abre chamados.
      Uma conta <strong>suspensa</strong> não entra no sistema, e a sessão aberta deixa de valer na requisição seguinte.</span>
    </div>
  </article>
</div>
<?php endif; ?>

<!-- ================================================= aba: Sessão ========= -->
<div class="aq-tabpanel" id="painel-seguranca" role="tabpanel" aria-labelledby="tab-seguranca" hidden>
  <div class="aq-grid aq-grid--2">
    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Sua conta']); ?>
      <div class="aq-form-row"><span>Nome</span><strong><?php aq_e($AQ_USER['name']); ?></strong></div>
      <div class="aq-form-row"><span>E-mail</span><strong><?php aq_e($AQ_USER['email']); ?></strong></div>
      <div class="aq-form-row"><span>Cargo</span><strong><?php aq_e(Guard::roleLabel($AQ_USER['role'])); ?></strong></div>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Regras da sessão', 'tip' => 'Configuradas em backend/config/session.php e aplicadas a cada requisição.']); ?>
      <div class="aq-form-row"><span>Cookie inacessível ao JavaScript (<code>HttpOnly</code>)</span><?php echo aq_badge($sessao['httponly'] ? 'Ativo' : 'Inativo', $sessao['httponly'] ? 'normal' : 'attention'); ?></div>
      <div class="aq-form-row"><span>Restrição a requisições de outros sites (<code>SameSite</code>)</span><strong><?php aq_e((string) $sessao['samesite']); ?></strong></div>
      <div class="aq-form-row"><span>Encerramento por inatividade</span><strong><?php echo (int) ($sessao['idle_timeout'] / 60); ?> minutos</strong></div>
      <div class="aq-form-row"><span>Duração máxima da sessão</span><strong><?php echo (int) ($sessao['absolute_timeout'] / 3600); ?> horas</strong></div>
    </article>
  </div>
</div>

<?php if ($podeCadastrar): ?>
<?php
/** Campo de formulário com rótulo e lugar para a mensagem de erro (preenchida por settings.js). */
$campo = static function (string $form, string $nome, string $rotulo, string $tipo = 'text', string $exemplo = '', string $extra = ''): void {
    $id = $form . '-' . $nome; ?>
        <div class="aq-field">
          <label class="aq-field__label" for="<?php aq_e($id); ?>"><?php aq_e($rotulo); ?></label>
          <input class="aq-input" type="<?php aq_e($tipo); ?>" id="<?php aq_e($id); ?>" name="<?php aq_e($nome); ?>"
                 placeholder="<?php aq_e($exemplo); ?>" style="width:100%;min-width:0" <?php echo $extra; // atributos fixos deste arquivo ?>>
          <span class="aq-field__error" data-error-for="<?php aq_e($nome); ?>" hidden></span>
        </div>
<?php };

/** Lista de seleção; sem opções, é preenchida por settings.js. @param array<string,string> $opcoes */
$lista = static function (string $form, string $nome, string $rotulo, array $opcoes = []): void {
    $id = $form . '-' . $nome; ?>
        <div class="aq-field">
          <label class="aq-field__label" for="<?php aq_e($id); ?>"><?php aq_e($rotulo); ?></label>
          <select class="aq-select" id="<?php aq_e($id); ?>" name="<?php aq_e($nome); ?>" style="width:100%;min-width:0">
            <?php foreach ($opcoes as $v => $t): ?><option value="<?php aq_e($v); ?>"><?php aq_e($t); ?></option><?php endforeach; ?>
          </select>
          <span class="aq-field__error" data-error-for="<?php aq_e($nome); ?>" hidden></span>
        </div>
<?php };

/** Abertura e fechamento comuns das janelas de cadastro. */
$abrir = static function (string $id, string $icone, bool $larga = false): void { ?>
<div class="aq-modal" id="modal-<?php aq_e($id); ?>" role="dialog" aria-modal="true" aria-labelledby="modal-<?php aq_e($id); ?>-titulo" hidden>
  <div class="aq-modal__box<?php echo $larga ? ' aq-modal__box--wide' : ''; ?>">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon($icone); ?></span>
      <h2 id="modal-<?php aq_e($id); ?>-titulo" data-field="<?php aq_e($id); ?>.title">—</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-<?php aq_e($id); ?>" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>
    <form id="form-<?php aq_e($id); ?>" class="aq-form-stack" novalidate> <?php /* enviado por settings.js; a validação é feita no servidor */ ?>
<?php };
$fechar = static function (string $id): void { ?>
      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-<?php aq_e($id); ?>">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('save'); ?><span>Salvar</span></button>
      </div>
    </form>
  </div>
</div>
<?php };

/* --------------------------------------------------------- janela: empresa */
$abrir('empresa', 'building');
$campo('empresa', 'name', 'Nome', 'text', 'Ex.: Hidrovale Energia', 'maxlength="120"');
$campo('empresa', 'manager', 'Responsável', 'text', 'Nome do responsável', 'maxlength="120"');
$lista('empresa', 'status', 'Situação', \Aquapulse\Repositories\RegistryRepository::COMPANY_STATUS);
$fechar('empresa');

/* --------------------------------------------------------- janela: represa */
$abrir('represa', 'waves', true);
?>
      <div class="aq-grid aq-grid--2">
        <?php
        $lista('represa', 'company_id', 'Empresa');
        $campo('represa', 'name', 'Nome', 'text', 'Ex.: Represa Santa Clara', 'maxlength="120"');
        $campo('represa', 'city', 'Cidade', 'text', 'Ex.: Rio Claro — SP', 'maxlength="120"');
        $campo('represa', 'basin', 'Bacia', 'text', 'Ex.: Bacia do Rio Claro', 'maxlength="120"');
        $campo('represa', 'lat', 'Latitude', 'text', 'Ex.: -22,3875', 'inputmode="text"');
        $campo('represa', 'lng', 'Longitude', 'text', 'Ex.: -47,6922', 'inputmode="text"');
        ?>
      </div>
      <p class="aq-field__label">Volumes (hm³ = milhões de m³)</p>
      <div class="aq-grid aq-grid--2">
        <?php
        $campo('represa', 'capacity_hm3', 'Capacidade total (hm³)', 'text', 'Ex.: 1500', 'inputmode="decimal"');
        $campo('represa', 'useful_volume_hm3', 'Volume útil (hm³)', 'text', 'Ex.: 1050', 'inputmode="decimal"');
        $campo('represa', 'technical_reserve_hm3', 'Reserva técnica (hm³)', 'text', 'Ex.: 184', 'inputmode="decimal"');
        $campo('represa', 'daily_consumption_hm3', 'Consumo médio por dia (hm³)', 'text', 'Ex.: 12,5', 'inputmode="decimal"');
        ?>
      </div>
      <p class="aq-field__label">Cotas (metros acima do nível do mar)</p>
      <div class="aq-grid aq-grid--2">
        <?php
        $campo('represa', 'cota_min_m', 'Cota mínima — 0% (m)', 'text', 'Ex.: 480', 'inputmode="decimal"');
        $campo('represa', 'cota_alert_m', 'Cota de alerta (m)', 'text', 'Ex.: 558', 'inputmode="decimal"');
        $campo('represa', 'cota_spill_m', 'Cota de vertimento (m)', 'text', 'Ex.: 565', 'inputmode="decimal"');
        $campo('represa', 'cota_max_m', 'Cota máxima — 100% (m)', 'text', 'Ex.: 580', 'inputmode="decimal"');
        ?>
      </div>
      <p class="aq-card__sub" data-reservoir-hint></p>
<?php
$fechar('represa');

/* --------------------------------------------------------- janela: limites */
$abrir('limites', 'sliders');
?>
      <p class="aq-field__label">pH — faixa ideal</p>
      <div class="aq-grid aq-grid--2">
        <?php
        $campo('limites', 'ph_min', 'Mínimo', 'text', 'Ex.: 6,5', 'inputmode="decimal"');
        $campo('limites', 'ph_max', 'Máximo', 'text', 'Ex.: 8,5', 'inputmode="decimal"');
        ?>
      </div>
      <p class="aq-field__label">Chuva acumulada em 24 h (mm)</p>
      <div class="aq-grid aq-grid--2">
        <?php
        $campo('limites', 'rain_attention', 'Atenção a partir de', 'text', 'Ex.: 20', 'inputmode="decimal"');
        $campo('limites', 'rain_critical', 'Crítico a partir de', 'text', 'Ex.: 60', 'inputmode="decimal"');
        ?>
      </div>
      <p class="aq-card__sub">Vale para todas as represas, a partir da próxima leitura.</p>
<?php
$fechar('limites');

/* ----------------------------------------------------------- janela: conta */
$abrir('conta', 'user-cog');
$campo('conta', 'name', 'Nome', 'text', 'Nome completo', 'maxlength="120"');
$campo('conta', 'email', 'E-mail', 'email', 'pessoa@empresa.com.br', 'maxlength="190" autocomplete="off"');
?>
      <div class="aq-grid aq-grid--2">
        <?php
        $lista('conta', 'role', 'Cargo');
        $lista('conta', 'status', 'Situação');
        ?>
      </div>
<?php $campo('conta', 'password', 'Senha', 'password', '', 'maxlength="72" autocomplete="new-password"'); ?>
      <fieldset class="aq-checklist" data-account-reservoirs>
        <legend class="aq-field__label">Represas que a conta vê</legend>
        <div data-reservoir-options></div>
        <span class="aq-field__error" data-error-for="reservoirs" hidden></span>
      </fieldset>
<?php $fechar('conta'); ?>
<?php endif; ?>

<?php aq_page_end(['scripts' => ['pages/settings.js']]);
