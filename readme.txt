=== PagBank Connect (PagSeguro) ===
Contributors: martins56
Tags: woocommerce, pagseguro, payment, pagbank, pix, boleto, visa, mastercard, cartão de crédito
Donate link: https://github.com/sponsors/r-martins
Requires at least: 4.0
Tested up to: 6.2
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv3
License URI: https://opensource.org/license/gpl-3-0/

Aceite Pix, Cartão de Crédito e Boleto de forma transparente e economize nas taxas oficiais.

== Description ==

https://www.youtube.com/watch?v=eN_WaK-1SQc

Esta é a forma mais fácil de integrar sua loja com PagBank (PagSeguro).
Ao instalar e configurar nossa integração, você pode aceitar Pix, Boleto e Cartão de Crédito com o meio de pagamento mais confiado pelos brasileiros.

Criado por Ricardo Martins, esta é a 4ª geração das integrações PagSeguro, disponibilizadas desde 2014 no Magento, e desde 2019 no WooCommerce. Mais de 20 mil lojas atendidas e mais de 200 milhões de reais transacionados em nossas integrações.

== Installation ==
=== Instalação manual ===
* Baixe o arquivo zip e descompacte ele em sua máquina
* Faça upload dos arquivos na pasta /wp-content/plugins/rm-pagbank
* Navegue até Plugins > Plugins instalados, e ative o plugin PagBank Connect

=== Instalação automática ===
* Navegue até Plugins > Adicionar Novo e procure por \"PagBank Ricardo Martins\"
* Clique no botão para instalar e ative o plugin

=== Configuração ===
* Ative o meio de pagamento navegando até WooCommerce > Configurações > Pagamentos, e ativando o PagBank Connect
* Clique no PagBank Connect para acessar as configurações do módulo
* Clique em \"Obter Credenciais\". Você será levado para nosso site, onde poderá escolher o modelo de recebimento (14 ou 30 dias) e então autorizar nossa aplicação.
* Ao clicar no modelo de recebimento desejado, você será levado para o site do PagBank, onde deverá se logar com sua conta e autorizar nossa aplicação.
* Em seguida, será levado(a) de volta para nosso site, onde deverá preencher as informações do responsável técnico por sua loja.
* Feito isso, sua *Connect Key* será exibida e enviada para o e-mail informado. Use ela nas configurações da sua loja.
* Salve as configurações e você está pronto para vender.
* Se desejar, configure opções de parcelamento, e validade do boleto e código pix de acordo com suas necessidades.

== Frequently Asked Questions ==
= Quais os requisitos para usar esta integração? =

* Ter WooCommerce 4.0 ou superior
* PHP 7.4 ou superior
* Ter uma conta Vendedor ou Empresarial no PagSeguro/PagBank
* [Autorizar nossa integração](https://pagseguro.ricardomartins.net.br/connect/autorizar.html) em sua conta PagBank.

= Como funcionam os descontos nas taxas? =

Ao usar nossas integrações no modelo de recebimento em 14 ou 30 dias, ao invés de pagar 4,99% ou 3,99%, você pagará cerca de 0,60% a menos e estará isento da taxa de R$0,40 por transação.

Taxas menores são aplicadas para transações parceladas, PIX e Boleto.

Consulte mais sobre elas no nosso site.

= Eu tenho uma taxa ou condição negociada menor que estas. O que faço? =

Ao usar nossa integração, nossas taxas e condições serão aplicadas ao invés das suas. Isto é, nas transações realizadas com nosso plugin.

É importante notar que taxas negociadas no mundo físico (moderninhas) não são aplicadas no mundo online.

Se mesmo assim você possuir uma taxa ou condição melhor, e se compromete a faturar mais de R$20 mil / mês (pedidos aprovados usando nossa integração), podemos incluir sua loja em uma aplicação especial.

Entre em [Contato conosco](https://pagsegurotransparente.zendesk.com/hc/pt-br/requests/new) para obter um convite e instruções. 
Ao fazer isso, informe o url da sua loja e e-mail do responsável por ela. O e-mail do responsável deve ser @urldaloja.xyz.

= Tenho outra pergunta não listada aqui =

Consulte nossa [Central de ajuda](https://pagsegurotransparente.zendesk.com/hc/pt-br/) e [entre em contato](https://pagsegurotransparente.zendesk.com/hc/pt-br/requests/new) conosco se não encontrar sua dúvida respondida por lá.

A maioria das dúvidas estão respondidas lá. As outras são respondidas em até 2 dias após entrar em contato.

= O plugin atualiza os status automaticamente? =

Sim. 

E quando há uma transação no PagBank, um link para ela é exibida na página do pedido. Assim você pode confirmar novamente o status do mesmo.

= Como posso testar usando a Sandbox? =

A Sandbox do PagBank encontra-se fora do ar há meses, e por isso não implementamos suporte a ela no plugin.

A equipe do PagBank está trabalhando numa correção.

Enquanto isso, você pode testar com dados reais e realizar o estorno. As tarifas e taxas são reembolsadas, não incidindo nenhum custo.

= Este é um plugin oficial? =

Não. Este é um plugin desenvolvido por Ricardo Martins, assim como outros para Magento e WooCommerce desenvolvidos no passado.

Apesar da parceria entre o desenvolvedor e o PagBank que concede descontos e benefícios, este não é um produto oficial.

PagSeguro e PagBank são marcas do UOL.


= Posso modificar e comercializar este plugin? =

O plugin é licenciado sob GPL v3. Você pode modificar e distribuir, contanto que suas melhorias e correções sejam contribuidas de volta com o projeto.

Você deve fazer isso através de Pull Requests ao [repositório oficial no github](https://github.com/r-martins/PagBank-WooCommerce).

== Changelog ==
= 4.0.0 =
* Lançamento da primeira versão Connect, com suporte a PIX, Cartão de crédito, e Boleto.

== Upgrade Notice ==
Ao atualizar nosso plugin, você se protege contra falhas de funcionamento e segurança e aumenta suas chances de conversão no momento mais importante do ciclo de vendas.

Ao atualizar versões majoritárias (ex: 3.5 para 4.0), certifique-se de testar rigorosamente sua integração, pois mudanças deste tipo tendem a trazer incompatibilidades entre versões.

== Screenshots ==
1. Cartão de Crédito na visão do cliente
2. PIX - Tela de Sucesso
3. Configurações de cartão de crédito
4. PIX e Boleto - Configurações
5. Admin - Tela do Pedido
