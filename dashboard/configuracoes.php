<?php
/** Aquapulse — Configurações. */
/*
 * Tela de consulta: empresas e represas, limites operacionais em vigor, contas
 * de acesso (só para administradores) e regras da sessão. Dados de
 * GET api/v1/settings.php, desenhados por assets/js/pages/settings.js.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

use Aquapulse\Support\Guard;

aq_page_start([
    'route'    => 'settings',
    'title'    => 'Configurações',
    'subtitle' => 'Consulte empresas, represas, limites e contas de acesso',
]);

$abas = [
    'empresas'  => ['label' => 'Empresas e represas', 'icon' => 'building'],
    'limites'   => ['label' => 'Limites operacionais', 'icon' => 'sliders'],
    'usuarios'  => ['label' => 'Usuários', 'icon' => 'user-cog'],
    'seguranca' => ['label' => 'Sessão', 'icon' => 'shield-lock'],
];

if (!Guard::isAdmin($AQ_USER)) {
    unset($abas['usuarios']);                                             // a lista de contas é só para administradores (a API também não a entrega)
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
      <?php echo aq_card_head(['title' => 'Empresas', 'tip' => 'Empresas que a sua conta pode consultar.']); ?>
      <div class="aq-field" style="margin-bottom:12px">
        <label class="aq-visually-hidden" for="busca-empresa">Buscar empresa</label>
        <input class="aq-input" type="search" id="busca-empresa" placeholder="Buscar empresa" style="width:100%">
      </div>
      <div data-companies></div> <?php /* clicar em uma empresa mostra os dados dela ao lado */ ?>
    </article>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Dados da empresa']); ?>
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
            'actions' => '<a class="aq-card__link" href="equipamentos.php">Equipamentos ' . aq_icon('arrow-right') . '</a>',
        ]); ?>
        <div data-company-reservoirs></div>
      </article>
    </div>
  </div>
</div>

<!-- ================================================= aba: Limites ======== -->
<div class="aq-tabpanel" id="painel-limites" role="tabpanel" aria-labelledby="tab-limites" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Limites operacionais em vigor', 'tip' => 'Valores lidos da tabela operational_limits.']); ?>
    <div data-limits></div>
    <div class="aq-note" style="margin-top:14px">
      <?php aq_the_icon('info'); ?>
      <span>Estes limites classificam os valores em todas as telas (Normal, Atenção, Crítico) e decidem quando um
      alerta é aberto. Ficam na tabela <code>operational_limits</code> do banco; cada alteração encerra o limite
      anterior e registra um novo, preservando o histórico.</span>
    </div>
  </article>
</div>

<!-- ================================================= aba: Usuários ======= -->
<?php if (isset($abas['usuarios'])): ?>
<div class="aq-tabpanel" id="painel-usuarios" role="tabpanel" aria-labelledby="tab-usuarios" hidden>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Contas de acesso', 'tip' => 'Contas cadastradas na tabela users.']); ?>
    <?php echo aq_table_open('Contas de acesso'); ?>
    <table class="aq-table">
      <thead>
        <tr><th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Perfil</th><th scope="col">Situação</th><th scope="col">Último acesso</th></tr>
      </thead>
      <tbody data-users></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <div class="aq-note" style="margin-top:14px">
      <?php aq_the_icon('info'); ?>
      <span><strong>Administrador</strong> vê todas as empresas e pode cadastrar equipamentos.
      <strong>Operador</strong> vê só as empresas vinculadas a ele, trata alertas e abre chamados.
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
      <div class="aq-form-row"><span>Perfil</span><strong><?php aq_e(Guard::roleLabel($AQ_USER['role'])); ?></strong></div>
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

<?php aq_page_end(['scripts' => ['pages/settings.js']]);
