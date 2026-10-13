# WooCommerce 5.x — dual PagBank / Vindi

Slug do plugin permanece `pagbank-connect`. Versão **5.0.0+**.

## Detecção

| Connect Key | Partner | Host |
|-------------|---------|------|
| `CON…` / `CONSANDBOX…` | PagBank | `ws…/pspro/v7/connect/` (**40** chars) |
| `CONVD…` / `CONVDSANDBOX…` | Vindi | `api…/v1/` — **40** chars prod + sandbox (igual PagBank; legado mais curto OK) |

Nunca chame a API Vindi com a Connect Key. Tokenização de cartão no browser usa `GET /tokenize/config` → `payment_profiles` (chave pública Vindi).

**Modelo C:** o plugin envia o **contrato canônico PB** (centavos, `CREDIT_CARD` / `CREDIT_CARD_3DS`, `gateway_token` / `payment_profile_id`). Keys `CONVD…` **não** passam pelo v7. Keys `CON…` continuam no formato/host PagBank (v7).

## Matriz classic × Blocks

| Recurso | Classic PagBank | Classic Vindi | Blocks PagBank | Blocks Vindi |
|---------|-----------------|---------------|----------------|--------------|
| PIX / boleto | ✓ | ✓ (`api/v1`) | ✓ | ✓ |
| Cartão novo (tokenização) | `encryptCard` | public `payment_profiles` | `encryptCard` | public `payment_profiles` (`vindiCheckout.js`) |
| 3DS | SDK PagBank | setup → DDC → enroll → challenge → validate | SDK PagBank | mesmos proxies AJAX Vindi |
| Tipo no pedido (3DS) | `CREDIT_CARD` + `authentication_method` | `CREDIT_CARD_3DS` + `three_ds` | idem | idem |
| Cartão salvo | tokens PagBank | `payment_profile_id` | tokens PagBank | `payment_profile_id` |
| Soft descriptor | ✓ | omitido (config na Vindi) | ✓ | omitido |
| Checkout redirect PagBank | ✓ | oculto | ✓ (`is_active`) | oculto (`hasHostedCheckout` = false) |
| Split / Dokan | ✓ | bloqueado | ✓ | bloqueado |
| Health CC (`isCcEnabledAndHealthy`) | sessão 3DS PagBank | `true` se CC enabled | — | — |
| Sandbox debug upstream | — | `POST orders?debug=1` automático (prod e sandbox) | — | idem |

## Recursos bloqueados na Vindi

- Checkout PagBank (`rm-pagbank-redirect`)
- Split / Dokan receivers PagBank

## Meta de pedidos

Helper canônico: `RM_PagBank\Helpers\OrderMeta`.

| Operação | Comportamento |
|----------|----------------|
| **Write** (PagBank) | `pagbank_*` / `_pagbank_*` |
| **Write** (Vindi / não-PagBank) | `pb_*` / `_pb_*` |
| **Read** | Prefixo do partner do pedido primeiro; fallback no outro (pedidos legados) |

Chaves lógicas omitem a marca, ex.: `order_id`, `_card_brand`, `partner`. Pedidos novos gravam:

- `partner` → `pagbank_partner` ou `pb_partner` (`pagbank` \| `vindi`)
- `connect_key_fp` → fingerprint (últimos 4 da key)

Vindi CC também pode gravar `_payment_profile_id`, `_card_3ds_payload`, `_payment_company_code`, `_card_gateway_token` (com prefixo `pb_` ou `_pb_`).

Queries (`meta_query`) devem usar `OrderMeta::metaQueryEquals()` / `metaQueryExists()` para cobrir os dois prefixos.

Hooks, gateway IDs (`rm-pagbank-*`) e opções WP **não** mudam.

## Recorrência

Tokens PagBank **não** migram. Após trocar para `CONVD…`, assinantes precisam atualizar o cartão. Option `rm_pagbank_recurring_needs_retoken`.

## Corte

Constante `RM_PagBank\Partner\Cutoff` = **5/nov/2026** (igual ao site). Só CTA — sem hard-kill no plugin.

## Contrato (API v1 / Vindi)

Pedidos Vindi usam o **contrato canônico PB** (`api.pbintegracoes.com/v1`), não o body cru da Vindi. Ver skill `pbintegracoes-agent-skills` → `docs/16-contract-architecture.md`. Cartão 3DS: `type: CREDIT_CARD_3DS`.

Para ver o que a API enviou ao adquirente: `POST /orders?debug=1` (ou header `X-PB-Debug: 1`) → `docs/17-debug-upstream.md`. O v7 PagBank **não** é alterado por este fluxo. Em pedidos Vindi o plugin anexa `debug=1` no `POST orders` (produção e sandbox).

## 3DS Vindi

`GET /v1/payment_methods?query=code:credit_card_3ds` via API PB. Se `credit_card_3ds.available`, pode habilitar (fluxo completo via proxies PB 3DS). Cobrança pós-auth: `CREDIT_CARD_3DS` + `three_ds` + `payment_profile_id`.

Classic: `public/js/creditcard.js`. Blocks: `public/js/blocks/vindiCheckout.js` + `checkout-blocks-cc.js` (rebuild: `npm run build`).

## Checklist QA rápido (Vindi)

- [ ] Classic CC sem 3DS (gateway_token)
- [ ] Classic CC com 3DS (challenge + `CREDIT_CARD_3DS`)
- [ ] Classic BELOW_MINIMUM_AMOUNT → re-token público, sem profile stale
- [ ] Blocks CC sem 3DS / com 3DS
- [ ] PIX e boleto thank-you + webhook
- [ ] Redirect e split **não** aparecem
- [ ] Cartão salvo Vindi (classic + Blocks `savedTokenComponent`)
- [ ] Troca CON→CONVD: retoken recorrência
- [ ] Log sandbox com `debug.upstream` redigido
