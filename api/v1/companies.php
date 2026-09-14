<?php
/** GET /api/v1/companies.php — lista as empresas disponíveis. */
/*
 * Chamado por: assets/js/api-client.js (AqApi.companies) para preencher o
 * seletor de empresa das telas. Não recebe parâmetros.
 * Resposta: { success, data: { companies: [...] }, meta: { company_id: 'all', ... } }
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';                                     // exige GET e sessão válida antes de qualquer coisa

use Aquapulse\Support\ApiResponse;

[$repo] = aq_api_boot();                                                 // pega só o repositório (primeiro item do array devolvido)

ApiResponse::success(
    ['companies' => $repo->companies()],                                 // todas as empresas cadastradas
    ['company_id' => 'all']                                              // meta: nenhum filtro de empresa aplicado
);
