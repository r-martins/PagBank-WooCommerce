=== PagBank Connect (PagSeguro) ===
Contributors: martins56
Tags: woocommerce, pagseguro, payment, pagbank, pix, boleto, visa, mastercard, cartão de crédito
Donate link: https://github.com/sponsors/r-martins
Requires at least: 4.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.2.1
License: GPLv3
License URI: https://opensource.org/license/gpl-3-0/

Aceite Pix, Cartão de Crédito e Boleto de forma transparente e economize nas taxas oficiais.

Use autenticação 3D Secure e reduza os custos com chargebacks.

== Description ==

https://www.youtube.com/watch?v=eN_WaK-1SQc

Esta é a forma mais fácil de integrar sua loja com PagBank (PagSeguro).
Ao instalar e configurar nossa integração, você pode aceitar Pix, Boleto e Cartão de Crédito com o meio de pagamento mais confiado pelos brasileiros.

Criado por Ricardo Martins, esta é a 4ª geração das integrações PagSeguro, disponibilizadas desde 2014 no Magento, e desde 2019 no WooCommerce. Mais de 20 mil lojas atendidas e mais de 200 milhões de reais transacionados em nossas integrações.

* Termos de uso e softwares terceiros *
Ao instalar e usar este plugin, você concorda com as [Regras de uso do PagBank](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), seu [Contrato de Serviço](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), [Termos de Segurança, Privacidade](https://pagseguro.uol.com.br/sobre/seguranca-e-privacidade) e [Compartilhamento](https://pagseguro.uol.com.br/sobre/regras-de-compartilhamento), bem como os [Termos de uso e Política de Privacidade](https://pagseguro.ricardomartins.net.br/terms.html) do desenvolvedor.

== Installation ==
=== Instalação manual ===
* Baixe o arquivo zip e descompacte ele em sua máquina
* Faça upload dos arquivos na pasta /wp-content/plugins/rm-pagbank
* Navegue até Plugins > Plugins instalados, e ative o plugin PagBank Connect
* Instale o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

=== Instalação automática ===
* Navegue até Plugins > Adicionar Novo e procure por \"PagBank Ricardo Martins\"
* Clique no botão para instalar e ative o plugin
* Repita o processo buscando e instalando o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

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
* Ter instalado o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

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

Sim. Basta clicar no botão 'Obter Connect Key para Testes' localizado nas configurações do plugin, seguir as instruções, e informar sua Connect Key de testes no campo indicado.

Um link para mais detalhes sobre como utilizar a Sandbox está disponível na página de configurações do plugin.

A equipe do PagBank está trabalhando numa correção.

Enquanto isso, você pode testar com dados reais e realizar o estorno. As tarifas e taxas são reembolsadas, não incidindo nenhum custo.

= Este é um plugin oficial? =

Não. Este é um plugin desenvolvido por Ricardo Martins, assim como outros para Magento e WooCommerce desenvolvidos no passado.

Apesar da parceria entre o desenvolvedor e o PagBank que concede descontos e benefícios, este NÃO é um produto oficial.

PagSeguro e PagBank são marcas do UOL.


= Posso modificar e comercializar este plugin? =

O plugin é licenciado sob GPL v3. Você pode modificar e distribuir, contanto que suas melhorias e correções sejam contribuidas de volta com o projeto.

Você deve fazer isso através de Pull Requests ao [repositório oficial no github](https://github.com/r-martins/PagBank-WooCommerce).

== Changelog ==
= 4.1.5 =
* Corrigido problema no carregamento inicial de parcelas. Uma mudança no PagBank fez com que as parcelas iniciais não fossem carregadas até que o cliente informasse o número do cartão.

= 4.1.3 =
* Corrigido problema que ocorria em alguns checkouts (ex: FunnelKit) onde, ao atualizar o meio de frete, o valor da parcela do cartão não era atualizado corretamente. Reportado por Philippe.

= 4.1.2 =
* Corrigido problema onde EnvioFacil não era suportado em aplicações autorizadas no modelo de recebimento em 30 dias (Reportado por Ligia Salzano)

= 4.1.1 =
* Corrigido problema de erro com unit.amount nos casos onde um ou mais produtos com valor zero está presente
* Administrador da loja agora recebe e-mail de novo pedido

= 4.1.0 =
* Mensagens mais amigáveis de erro ajudam o cliente a saber que parâmetro precisa ser corrigido
* Agora há opção de [ocultar o endereço de entrega](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20835022998029), evitando erros de validação de endereço em vários cenários.

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
6. Envio Fácil
7. Autenticação 3D Secure
