# Documentação do desenvolvedor


## Git hooks e compilação
Para compilar os arquivos frontend digite `npm run build` no terminal, na raiz do plugin. 
Se desejar automatizar este processo, copie o arquivo `./git-hooks/post-checkout` para `.git/hooks/` e torne-o executável (`chmod +x .git/hooks/post-checkout`).
Ao fazer isso, toda vez que você fizer um checkout, o hook irá compilar os arquivos frontend automaticamente.


## Blueprint.json
Localizado em assets/blueprint.json, é o arquivo que contém a receita de bolo para rodar o plugin em modo preview no playground.wordpress.net.

Para testar seu conteúdo, execute o método `btoa` no console do navegador ou no node e cole seu conteúdo após `https://playgroun.wordpress.net/#`.

Exemplo:
btoa(`conteudo do blueprint.json`);

https://playground.wordpress.net/#ewogICIkc2NoZW1hIjogImh0dHBzOi8vcGxheWdyb3VuZC53b3JkcHByZXNzLm5ldC9ibHVlcHJpbnQtc2NoZW1hLmpzb24iLAogICJzdGVwcyI6IFsKICAgIHsKICAgICAgInN0ZXAiOiAiaW1wb3J0V29yZFByZXNzRmlsZXMiLAogICAgICAid29yZFByZXNzRmlsZXNaaXAiOiB7CiAgICAgICAgInJlc291cmNlIjogInVybCIsCiAgICAgICAgInVybCI6ICJodHRwczovL3d3dy5kcm9wYm94LmNvbS9zY2wvZmkvc3RsdnVrMTg4NWZ5NDg1YW9scmFsL3BhZ2JhbmstY29ubmVjdC1wbGF5Z3JvdW5kLnppcD9ybGtleT11ZHl6YWtiMm0yemg3emFpbnVjYXZtOHZsbSZkbD0xIgogICAgICB9CiAgICB9LAogICAgewogICAgICAic3RlcCI6ICJ3cC1jbGkiLAogICAgICAiY29tbWFuZCI6ICJ3cCBwbHVnaW4gdXBkYXRlIC0tYWxsIgogICAgfQogIF0KfQ==

O console do navegador poderá indicar se há algum erro no blueprint.

## Testando com Docker / WS local (5.x)

Não edite `Helpers/Api.php` na mão. Em `wp-config.php` (somente `define` — **nunca** `add_filter` ali):

```php
define('RM_PAGBANK_WS_URL', 'https://v7.test/pspro/v7/connect/');
define('RM_PAGBANK_API_URL', 'https://api.pbintegracoes.test/v1/');
define('RM_PAGBANK_SSLVERIFY', false); // obrigatório com certs locais
```

O PHP do Woo roda **dentro** do container. Entradas do Mac (`*.test` → `127.0.0.1`) quebram o cURL (curl error 7). No `docker-compose.yml` do Woo, use:

```yaml
extra_hosts:
  - "api.pbintegracoes.test:host-gateway"
  - "v7.test:host-gateway"
```

Isso aponta os nomes para o host (nginx `:443`). Depois: `docker compose up -d wordpress`.

Alternativa (HTTP direto no Apache do WS, sem TLS):

```php
define('RM_PAGBANK_WS_URL', 'http://ws-pagseguro/pspro/v7/connect/');
// API v1 exige Host api.pbintegracoes.test — prefira extra_hosts + HTTPS acima.
```

Filtros (só em plugin/theme/`mu-plugins`, depois do WP carregar):

```php
add_filter('rm_pagbank_ws_url', fn () => 'https://v7.test/pspro/v7/connect/');
add_filter('rm_pagbank_api_url', fn () => 'https://api.pbintegracoes.test/v1/');
add_filter('rm_pagbank_sslverify', '__return_false');
```

- Connect Key **PagBank** (`CON…` / `CONSANDBOX…`) → `RM_PAGBANK_WS_URL` (v7)
- Connect Key **Vindi** (`CONVD…` / `CONVDSANDBOX…`) → `RM_PAGBANK_API_URL` (API v1)

## Dual partner (5.0+)

Ver `src/Partner/` (`PartnerDetector`, `Capabilities`, `Cutoff`, `Branding`) e o playbook em `docs/WOO-5-DUAL-PARTNER.md`.
