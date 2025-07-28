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

https://playground.wordpress.net/#ewogICIkc2NoZW1hIjogImh0dHBzOi8vcGxheWdyb3VuZC53b3JkcHJlc3MubmV0L2JsdWVwcmludC1zY2hlbWEuanNvbiIsCiAgInN0ZXBzIjogWwogICAgewogICAgICAic3RlcCI6ICJpbXBvcnRXb3JkUHJlc3NGaWxlcyIsCiAgICAgICJ3b3JkUHJlc3NGaWxlc1ppcCI6IHsKICAgICAgICAicmVzb3VyY2UiOiAidXJsIiwKICAgICAgICAidXJsIjogImh0dHBzOi8vd3d3LmRyb3Bib3guY29tL3NjbC9maS9zdGx2dWsxODg1Znk0ODVhb2xyYWwvcGFnYmFuay1jb25uZWN0LXBsYXlncm91bmQuemlwP3Jsa2V5PXVkeXpha2IybTJ6aDd6YWludWNhdm04dmwmZGw9MSIKICAgICAgfQogICAgfSwKICAgIHsKICAgICAgInN0ZXAiOiAid3AtY2xpIiwKICAgICAgImNvbW1hbmQiOiAid3AgcGx1Z2luIHVwZGF0ZSAtLWFsbCIKICAgIH0KICBdCn0=

O console do navegador poderá indicar se há algum erro no blueprint.

## Testando com Docker
Pra enviar requests pro docker, 2 modificações precisam ser feitas em Helpers/Api.php.
1. Trocar URL base para `https://web/connect/`
2. Adicionar options sslverify = false nos options do método get e post.