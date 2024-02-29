=== PagSeguro / PagBank Connect ===
Contributors: martins56
Tags: woocommerce, pagseguro, payment, pagbank, pix, boleto, visa, mastercard, cartão de crédito
Donate link: https://github.com/sponsors/r-martins
Requires at least: 4.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.4.4
License: GPLv3
License URI: https://opensource.org/license/gpl-3-0/

PIX, Cartão de Crédito, Boleto + Envio Fácil e ainda economize nas taxas oficiais do PagSeguro.

Autenticação 3D: menos chargeback e mais aprovações.

== Description ==

Esta é a forma mais fácil de integrar sua loja com PagBank (PagSeguro).
Ao instalar e configurar nossa integração, você pode aceitar Pix, Boleto e Cartão de Crédito com o meio de pagamento mais confiado pelos brasileiros.

https://www.youtube.com/watch?v=wnzA0KQZCQs

Criado por Ricardo Martins, esta é a 4ª geração das integrações PagSeguro, disponibilizadas desde 2014 no Magento, e desde 2019 no WooCommerce. Mais de 20 mil lojas atendidas e mais de 200 milhões de reais transacionados em nossas integrações.

https://www.youtube.com/watch?v=eN_WaK-1SQc

* Termos de uso e softwares terceiros
Ao instalar e usar este plugin, você concorda com as [Regras de uso do PagBank](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), seu [Contrato de Serviço](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), [Termos de Segurança, Privacidade](https://pagseguro.uol.com.br/sobre/seguranca-e-privacidade) e [Compartilhamento](https://pagseguro.uol.com.br/sobre/regras-de-compartilhamento), bem como os [Termos de uso e Política de Privacidade](https://pagseguro.ricardomartins.net.br/terms.html) do desenvolvedor.

== Features ==
* Suporte a PIX, Cartão de Crédito e Boleto
* Suporte a [recorrência (assinaturas)](https://pagsegurotransparente.zendesk.com/hc/pt-br/sections/20410120690829-Recorr%C3%AAncia-e-Clube-de-Assinatura-com-WooCommerce), sem depender de outros plugins
* Integração com [Envio Fácil](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19944920673805-Envio-F%C3%A1cil-com-WooCommerce) (economize até 70% no frete com Correios e Jadlog) sem precisar de contrato
* Suporte a [autenticação 3D Secure](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/22375922278157-Autentica%C3%A7%C3%A3o-3DS-Sua-prote%C3%A7%C3%A3o-contra-Chargeback) (reduza chargebacks e aumente suas aprovações)
* Diversas [opções de parcelamento](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945359660173-Op%C3%A7%C3%B5es-de-Parcelamento)
* Suporte a [descontos no boleto e pix](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945430928909-Oferecer-Desconto-Pix-e-Boleto)
* Permite definir validade de boletos e código PIX
* Atualições automáticas de status de pedidos
* Configure como quer exibir o nome da loja na fatura do cartão de crédito
* Diversas [opções de configuração de endereço](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20835022998029-Configura%C3%A7%C3%B5es-de-Endere%C3%A7o-de-Entrega)



== Installation ==
=== Instalação automática ===
* Navegue até Plugins > Adicionar Novo e procure por "PagBank Ricardo Martins"
* Clique no botão para instalar e ative o plugin
* Repita o processo buscando e instalando o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

=== Instalação manual ===
* Baixe o [arquivo zip](https://codeload.github.com/r-martins/PagBank-WooCommerce/zip/refs/heads/master) e descompacte ele em sua máquina
* Faça upload dos arquivos na pasta /wp-content/plugins/pagbank-connect, usando seu FTP
* Navegue até Plugins > Plugins instalados, e ative o plugin PagBank Connect
* Instale o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

=== Configuração ===
* Ative o meio de pagamento navegando até WooCommerce > Configurações > Pagamentos, e ativando o PagBank Connect
* Clique no PagBank Connect para acessar as configurações do módulo
* Clique em "Obter Credenciais". Você será levado para nosso site, onde poderá escolher o modelo de recebimento (14 ou 30 dias) e então autorizar nossa aplicação.
* Ao clicar no modelo de recebimento desejado, você será levado para o site do PagBank, onde deverá se logar com sua conta e autorizar nossa aplicação.
* Em seguida, será levado(a) de volta para nosso site, onde deverá preencher as informações do responsável técnico por sua loja.
* Feito isso, sua *Connect Key* será exibida e enviada para o e-mail informado. Use ela nas configurações da sua loja.
* Salve as configurações e você está pronto para vender.
* Se desejar, configure opções de parcelamento, e validade do boleto e código pix de acordo com suas necessidades.

== Frequently Asked Questions ==
= PagSeguro ou PagBank? =

Em 2023 o PagBank e o PagSeguro se tornaram uma única empresa: PagBank.

E não se preocupe, os serviços que você usa não serão afetados.

= Quais os requisitos para usar esta integração? =

* Ter WooCommerce 4.0 ou superior
* PHP 7.4 ou superior
* Ter uma conta Vendedor ou Empresarial no PagSeguro/PagBank
* [Autorizar nossa integração](https://pagseguro.ricardomartins.net.br/connect/autorizar.html) em sua conta PagBank.
* Ter instalado o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

= Como funcionam os descontos nas taxas? =

Ao usar nossas integrações no modelo de recebimento em 14 ou 30 dias, ao invés de pagar 4,99% ou 3,99%, você pagará cerca de 0,60% a menos e estará isento da taxa de R$0,40 por transação.

Taxas menores são aplicadas para transações parceladas, PIX e Boleto. PIX e Boleto também possuem prazos menores de recebimento.

Consulte mais sobre elas no [nosso site](https://pagseguro.ricardomartins.net.br/connect/autorizar.html).

= Eu tenho uma taxa ou condição negociada menor que estas. O que faço? =

Ao usar nossa integração, nossas taxas e condições serão aplicadas ao invés das suas. Isto é, nas transações realizadas com nosso plugin.

É importante notar que taxas negociadas no mundo físico (moderninhas) não são aplicadas no mundo online.

Se mesmo assim você possuir uma taxa ou condição melhor, e se compromete a faturar mais de R$20 mil / mês (pedidos aprovados usando nossa integração), podemos incluir sua loja em uma aplicação especial.

Ao [autorizar sua conta](https://pagseguro.ricardomartins.net.br/connect/autorizar.html), escolha a opção "Suas condições e taxas".

Sua Connect key será gerada respeitando as taxas e condições negociadas que você tem com o PagSeguro/PagBank.

= Tenho outra pergunta não listada aqui =

Consulte nossa [Central de ajuda](https://pagsegurotransparente.zendesk.com/hc/pt-br/) e [entre em contato](https://pagsegurotransparente.zendesk.com/hc/pt-br/requests/new) conosco se não encontrar sua dúvida respondida por lá.

A maioria das dúvidas estão respondidas lá. As outras são respondidas em até 2 dias após entrar em contato.

= O plugin atualiza os status automaticamente? =

Sim. 

E quando há uma transação no PagBank, um link para ela é exibida na página do pedido. Assim você pode confirmar novamente o status do mesmo.

Caso utilize Cloudflare ou CDN, certifique-se de [configurá-lo corretamente](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/115002699823-Usu%C3%A1rios-Cloudflare-e-CDN-s) e liberar os IPs do PagSeguro para evitar bloqueios nas notificações.

= Posso testar usando a Sandbox? =

Sim. Basta clicar no botão 'Obter Connect Key para Testes' localizado nas configurações do plugin, seguir as instruções, e informar sua Connect Key de testes no campo indicado.

Um link para mais detalhes sobre como utilizar a Sandbox está disponível na página de configurações do plugin.

Você também pode testar com dados reais e realizar o estorno. As tarifas e taxas são reembolsadas, não incidindo nenhum custo.

= Este é um plugin oficial? =

Não. Este é um plugin desenvolvido por Ricardo Martins, assim como outros para Magento e WooCommerce desenvolvidos no passado.

Apesar da parceria entre o desenvolvedor e o PagBank que concede descontos e benefícios, este NÃO é um produto oficial.

PagSeguro e PagBank são marcas do UOL.


= Posso modificar e comercializar este plugin? =

O plugin é licenciado sob GPL v3. Você pode modificar e distribuir, contanto que suas melhorias e correções sejam contribuidas de volta com o projeto.

Você deve fazer isso através de Pull Requests ao [repositório oficial no github](https://github.com/r-martins/PagBank-WooCommerce).

== Changelog ==

= 4.4.4 =
* Correção: em alguns cenários, quando havia algum erro e o cliente tentava finalizar um pedido com cartão novamente, um erro 40002 em payment_method.card.encrypted era exibido
* Melhoria em mensagem específica de erro quando o e-mail do cliente está incorreto


= 4.4.3 =
* Correção: ao tentar pagar um pedido novamente descontos de PIX ou Boleto eram re-aplicados. Reportado por Fabio (Kaizen digital) e Igor Onofri.

= 4.4.2 =
* Correção: ao tentar finalizar pagamentos com Boleto usando CNPJ um erro 40002 era exibido. Reportado por Patrick (ctec).

= 4.4.1 =
* Melhoria: Quando o usuário começava a digitar um cartão, o sistema detectava a bandeira como Visa, mesmo que não fosse um cartão deste tipo. Só após o sexto dígito é que a bandeira correta era identificada. Reportado por Lucas Melo.
* Correção: Logo PagBank aparecia muito pequeno em dispositivos móveis. Reportado por Jhonny Robson
* Correção: o campo de Adicionar X dias à estimativa de frete trazia o cep da loja como placeholder. (oops) Embora pudesse causar certa confusão, o valor não era somado (indevidamente) à estimativa.
* Correção: em alguns cenários de compra de Produtos Virtuais, atributos de endereço de cobrança não eram repassados corretamente para o endereço de envio, ocasionando erro na finalização de compra (Bairro, Numero, etc). Reportado por Marcio Gazetta.

= 4.4.0 =
* Agora é possível adicionar X dias ao cálculo de frete (Envio Fácil) 
* Agora é possível ajustar o preço do frete de forma fixa ou percentual, como desconto ou acréscimo (Envio Fácil)
* Redução de requisições ajax no checkout com 3d
* Melhoria para contornar erro de credit_car_bin not found quando estamos testando em Sandbox (já que o PagBank não atualiza os cartões de teste).
* Pequenos ajustes estéticos de código e espaçamentos, e conformidade com padrões do PCP.
* Melhorias de segurança.
* Correção de erro em payment_method.authentication_method.id invalid_parameter quando alguns pagamentos em 3D com parcelamento maior que 1 parcela era finalizado.
* Corrige problema com exibição de validade do código pix quando era maior que 60 minutos e menos que 1 dia. Nesses cenários a validade do Pix não era informada ao cliente no frontend.
* Corrigindo erro Call to undefined function wp_add_notice em alguns cenários no admin.
* Melhoria em mensagens de erro de e-mail inválido, nome do titular do cartão inválido e cartão criptografado com problema.

= 4.3.1 =
* Correção em erro grave que eliminava CSS de várias páginas causando quebras de layout em vários cenários, mesmo onde o plugin não era inserido (Reportado por William T. e outros). O problema foi introduzido na versão 4.3.0.
* Ajustes no CSS de configuracao de cor dos icones, logo e afins
* Agora é possível escolher a cor dos ícones via admin
* Corrigido css que fazia o nome ou logo do PagBank aparecerem abaixo do radio button
* Em algumas situações, era provável que uma autenticação 3D fosse rejeitada ocorrendo erro 'authentication_method.id' invalido. É provável que isso ocorresse quando um consumidor esperasse mais de 11 minutos para preencher e concluir os dados do checkout, e somente quando um outro cliente chegou ao checkout há menos de 20 minutos mas não concluiu uma compra (Reportado por William T.). Também melhoramos a mensagem de erro nestes casos, recomendando atualização de página.
* Melhoria na forma como os ícones são inseridos para possibilitar personalização via css (não usarmos mais <img..)
* Correção em aviso de depreciação do jQuery .change (embora isso não afetava o funcionamento do plugin)
* Ao invés de mostrar JOSÉ DA SILVA como placeholder do campo de titular de cartão, agora exibimos "como gravado no cartão", e o nome sempre será em caixa alta
* Ícones de pagamento podem ter ficado maior em alguns checkouts. Instruções para personalização foram adicionados ao admin.

= 4.3.0 =
* Adicionado suporte a venda recorrente (clube de assinatura) sem depender de outros plugins

= 4.2.14 =
* Correção: dependendo do valor total do pedido, quando a autenticação 3d estava ativada, o erro 'amount.value must be an integer' era exibido
* Melhoria: pequeno ajuste na mensagem de erro acima
* Melhoria: algumas atualizações do plugin não surtiam efeito para alguns usuários devido a cache forçado do navegador ou configuração de outros plugins

= 4.2.13 =
* Correção: Parametro inválido (payment_method.card.encrypted) era exibido em alguns cenários, impedindo a finalização da compra.
* Correção: tags html eram exibidas nas configurações do Envio Facil no admin. Não afetava o funcionamento, mas era feio. :)
* Melhoria: na ferramenta de diagnóstico, para exibir configurações de 3d secure

= 4.2.12 =
* Correção/Melhoria: quando usado em conjunto com alguns plugins, em alguns casos não era exibido na lista de meios de pagamento disponíveis, e as configurações do plugin não eram carregadas. (Reportado por Leonardo)

= 4.2.11 =
* Correção: imagem e códigos pix e boleto não eram exibidos se recurso de High-performance order storage (HPOS) estivesse ativado

= 4.2.10 =
* Correção: compras com CNPJ exibiam erro 40002. (Reportado por Therus)

= 4.2.9 =
* Re-correção do problema anterior. Algo deu errado na publicação.

= 4.2.8 =
* Correção: após 4.2.6, ao desabilitar o 3d secure, o cartão de crédito deixava de ser exibido no checkout. (Reportado por Junior Marins)

= 4.2.7 =
* Correção: Erro era exibido no admin após última atualização. :O (Fatal error: Uncaught Error: Call to a member function get_total() on null) 

= 4.2.6 =
* Melhoria: PagBank deixa de ser exibido se valor total do pedido for menor que R$1,00, evitando erro 40002 charges[0].amount.value is invalid. PagBank não aceita pedidos abaixo deste valor.
* Correção: Sempre usávamos kg como medida de peso para cálculo do envio fácil, fazendo com que lojas que configuraram o peso em outra medida tivesse o cálculo incorreto (geralmente não devolvendo nenhuma cotação).
* Correção: quando um cliente decide pagar um pedido posteriormente indo em Minha conta > Pedidos > Pagar, o dropdown de parcelas do cartão não carregava como esperado. (Reportado por Therus)
* Correção: em warning logado (undefined index $active['boleto'] e $active['pix']
* Correção: exceção era gerada se a API do 3D Secure estiver fora do ar e o recurso ativo.
* Melhoria: caso a opção de 3D Secure esteja ativo, mas a API 3D estiver fora do ar, o cartão de crédito não será exibido para o cliente caso a opção de "Permitir concluir" não estiver marcada. Nestes casos, um aviso será exibido pedindo que recarregue a página. (Reportado por Martin)


= 4.2.5 =
* Correção: em versões antigas do WooCommerce (ex: 6.4), em pedidos com cartao, o checkout era recarregado ao tentar finalizar compra, sem concluir o pedido. (#2)
* Melhorias e refatoracao no JS de cartão de crédito, que agora não é mais inserido na página de sucesso desnecessariamente.
* Mais melhorias em possíveis mensagens de erro no retorno de validações 3DSecure.
* Mais ajustes pequenos em traduções, solicitados pela equipe do WordPress

= 4.2.2 =
* Ajustes diversos para compatibilidade com requisitos do WP Marketplace
* Corrigido problema onde, em alguns cenários, logo após desativar a autenticação 3D, o plugin ainda tentava autenticar usando este método e gerando erro

= 4.2.1 =
* Pequenos ajustes de compatibilidade para estar de acordo com os requisitos do marketplace do WP Marketplace

= 4.2.0 =
* Adicionado suporte a autenticação 3D Secure, reduzindo drasticamente seus custos com chargebacks, e aumentando significativamente a taxa de aprovação.
* Passamos a cachear algumas chamadas repetidas, aumentando significativamente a performance do processo de checkout.

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
8. Pedidos Recorrentes (assinaturas)
