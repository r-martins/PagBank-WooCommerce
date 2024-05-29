=== PagSeguro / PagBank Connect ===
Contributors: martins56
Tags: pagseguro, pagbank, pix, cartão de crédito, pagamento
Donate link: https://github.com/sponsors/r-martins
Requires at least: 4.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 4.11.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
PIX, Cartão de Crédito, Boleto, Recorrência + Envio Fácil e com taxas ainda menores no PagSeguro.
Autenticação 3D: menos chargeback e mais aprovações.

== Description ==

**Aceite PagSeguro e PagBank (Pix, Cartão de Crédito, Boleto) em sua loja WooCommerce.**

Esta é a **forma mais fácil de integrar sua loja com PagBank (PagSeguro)**.
Ao instalar e configurar nossa integração, você pode aceitar Pix, Boleto e Cartão de Crédito com o meio de pagamento mais confiado pelos brasileiros.

https://www.youtube.com/watch?v=wnzA0KQZCQs

Criado por Ricardo Martins (**Parceiro oficial PagBank/PagSeguro desde 2015**), esta é a 4ª geração das integrações PagSeguro, disponibilizadas desde 2014 no Magento, e desde 2019 no WooCommerce. Mais de 20 mil lojas atendidas e mais de 200 milhões de reais transacionados em nossas integrações.

Além disso, você também pode aceitar pagamentos recorrentes e criar clubes de assinatura sem depender de plugins de terceiros.

https://www.youtube.com/watch?v=FOPwBTRryNM


**Problemas com aprovação de pagamentos e chargebacks em transações com cartão de crédito?**

Nossa integração suporta [autenticação 3D Secure](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/22375922278157-Autentica%C3%A7%C3%A3o-3DS-Sua-prote%C3%A7%C3%A3o-contra-Chargeback), que reduz drasticamente seus custos com chargebacks, e aumenta significativamente a taxa de aprovação.

Tudo pra você vender mais com PagBank(PagSeguro) sem sequer precisar se preocupar em contratar um serviço de antifraude.


* Termos de uso e softwares terceiros
Ao instalar o plugin PagBank Connect, você concorda com as [Regras de uso do PagBank](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), seu [Contrato de Serviço](https://pagseguro.uol.com.br/sobre/contrato-de-servicos), [Termos de Segurança, Privacidade](https://pagseguro.uol.com.br/sobre/seguranca-e-privacidade) e [Compartilhamento](https://pagseguro.uol.com.br/sobre/regras-de-compartilhamento), bem como os [Termos de uso e Política de Privacidade](https://pagseguro.ricardomartins.net.br/terms.html) do desenvolvedor.

== Features ==
* Suporte a PIX, Cartão de Crédito e Boleto
* Suporte a [recorrência (assinaturas)](https://pagsegurotransparente.zendesk.com/hc/pt-br/sections/20410120690829-Recorr%C3%AAncia-e-Clube-de-Assinatura-com-WooCommerce), sem depender de outros plugins
* Integração com [Envio Fácil](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19944920673805-Envio-F%C3%A1cil-com-WooCommerce) (economize até 70% no frete com Correios e Jadlog) sem precisar de contrato
* Suporte a [autenticação 3D Secure](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/22375922278157-Autentica%C3%A7%C3%A3o-3DS-Sua-prote%C3%A7%C3%A3o-contra-Chargeback) (reduza chargebacks e aumente suas aprovações)
* Diversas [opções de parcelamento](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945359660173-Op%C3%A7%C3%B5es-de-Parcelamento)
* Suporte a [descontos no boleto e pix](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945430928909-Oferecer-Desconto-Pix-e-Boleto)
* Permite definir validade de boletos e código PIX
* Atualições automáticas de status de pedidos
* Configure como quer exibir o [nome da loja na fatura do cartão de crédito](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945038495629-Identificador-na-fatura)
* Diversas [opções de configuração de endereço](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20835022998029-Configura%C3%A7%C3%B5es-de-Endere%C3%A7o-de-Entrega)
* Suporte a [High-Performance Order Storage (HPOS)](https://woo.com/document/high-performance-order-storage/): Este plugin é otimizado para ambientes com High-Performance Order Storage, garantindo um manuseio rápido e eficiente de seus pedidos WooCommerce.
* Exibição de [informações de parcelas na página de produto em 3 formatos diferentes](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/26223028355597-Exibir-informa%C3%A7%C3%B5es-de-parcelamento-na-p%C3%A1gina-de-produto)
* Permite exibir os meios de pagamento de [forma individual ou agrupada](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/26581505001357-Separar-meios-de-pagamento) (melhor experiência)


== Installation ==
=== Instalação automática via painel ===
* Navegue até Plugins > Adicionar Novo e procure por "PagBank Ricardo Martins"
* Clique no botão para instalar e ative o plugin
* Repita o processo buscando e instalando o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

=== WP-CLI ===
Você pode instalar o plugin usando o [WP-CLI](https://wp-cli.org/). 

* Basta rodar o comando `wp plugin install pagbank-connect --activate`. Adicione `--allow-root` se estiver rodando o comando como root.
* Repita o processo para instalar o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) com o comando `wp plugin install woocommerce-extra-checkout-fields-for-brazil --activate` caso ainda não tenha ele instalado.

=== Instalação manual ===
* Baixe o [arquivo zip](https://codeload.github.com/r-martins/PagBank-WooCommerce/zip/refs/heads/master) e descompacte ele em sua máquina
* Faça upload dos arquivos na pasta /wp-content/plugins/pagbank-connect, usando seu FTP
* Navegue até Plugins > Plugins instalados, e ative o plugin PagBank Connect
* Instale o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

=== A gente instala pra você 🙀 ===
Se você preferir, podemos instalar e configurar o plugin para você sem nenhum custo.

[Saiba mais](https://pagseguro.ricardomartins.net.br/woocommerce/agenteinstala.html). 

=== Configuração ===
* Ative o meio de pagamento navegando até WooCommerce > Configurações > Pagamentos, e ativando o PagBank Connect
* Clique no PagBank Connect para acessar as configurações do módulo
* Clique em "Obter Connect Key". Você será levado para nosso site, onde poderá escolher o modelo de recebimento (14 ou 30 dias) e então autorizar nossa aplicação.
* Ao clicar no modelo de recebimento desejado, você será levado(a) para o site do PagBank, onde deverá se logar com sua conta e autorizar nossa aplicação.
* Em seguida, será levado(a) de volta para nosso site, onde deverá preencher as informações do responsável técnico por sua loja.
* Feito isso, sua *Connect Key* será exibida e enviada para o e-mail informado. Use ela nas configurações da sua loja.
* Salve as configurações e sua loja está pronta para vender.
* Se desejar, configure [opções de parcelamento](https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945359660173-Op%C3%A7%C3%B5es-de-Parcelamento), e validade do boleto e código pix de acordo com suas necessidades.

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


= O plugin é compatível com o WooCommerce Subscriptions? =

Você não precisa do plugin WooCommerce Subscriptions ou nenhum outro para aceitar [pagamentos recorrentes com nossa integração](https://pagsegurotransparente.zendesk.com/hc/pt-br/sections/20410120690829-Recorr%C3%AAncia-e-Clube-de-Assinatura-com-WooCommerce).


= Posso modificar e comercializar este plugin? =

O plugin é licenciado sob GPL v3. Você pode modificar e distribuir, contanto que suas melhorias e correções sejam contribuidas de volta com o projeto.

Você deve fazer isso através de Pull Requests ao [repositório oficial no github](https://github.com/r-martins/PagBank-WooCommerce).

== Changelog ==
= 4.11.2 =
* Correção: em alguns cenários, o campo de CPF, número do endereço e bairro não eram enviados corretamente ocasionando falha no fechamento do pedido (especialmente em Boletos).

= 4.11.1 = 
* Correção: nosso validador de chaves pix adicionado na versão anterior apontava para pedidos não-pix
* Correção: a mensagem de validação do pix não era ignorada em alguns cenários após dispensá-la

= 4.11.0 =
* Melhoria: agora o plugin exibe uma mensagem de erro no admin caso o código PIX esteja sendo gerado incorretamente por conta de algum problema em sua conta PagBank (geralmente porque você não cadastrou a chave aleatória).
* Correção: o valor dos produtos era informado de forma incorreta ao PagBank quando múltiplos do mesmo item estavam presentes no pedido (embora o valor cobrado estivesse correto).

= 4.10.2 =
* Correção em erro no cálculo de parcelas. Em alguns cenários, dependendo das regras de parcelamento, quando o total de parcelas sem juros era = 1, poderia ocasionar erro na pagina de produto (se as parcelas estivessem sendo exibidas la) e no dropdown de parcelamento do checkout.
* Correções diversas em warnings e notices em versões mais novas do PHP 8.1 e 8.2 que poderiam aparecer no admin, e em algumas etapas do pedido quando modo debug estava ativo. 

= 4.10.1 =
* Correção de erro "get_cart was called incorrectly" era exibido quando usado em conjunto com alguns outros plugins (como Mercado Pago), em alguns casos quebrando o carrinho.
* Alteramos a forma como o uso de shortcode de parcelamento é usado. Agora você deve habilitar ele nas configurações. Isso evita que ele seja adicionado em duplicidade.
* Corrigido falha na compra de produto recorrente quando meios de pagamento eram configurados para ser exibidos de forma separada. A mensagem Método de pagamento inválido era exibida.
* Melhoramos a descrição de alguns dos campos de configuração de cartão de crédito, a fim de deixar mais claro o que cada um faz e com mais links para documentação.


= 4.10.0 =
* Emails: agora o administrador e cliente só receberão e-mails notificando que um pedido foi criado se o mesmo tiver sido pago.
* Correção/Mudança: agora pessoas com permissão de gerente de loja e administradores poderão ter acesso ao menu PagBank. Antes somente administradores tinham acesso.
* Agora é possível usar o shortcode [rm_pagbank_credit_card_installments] para exibir as parcelas de um produto em layouts personalizados.
* Corrigido Erro na exibição das parcelas quando opção 'Texto com parcela máxima' era selecionado em alguns cenários.


= 4.9.3 =
* Correção: quando usado em conjunto com alguns plugins, chamadas ao jQuery falhavam e impediam a finalização do checkout com erros na criptografia do cartão, entre outras coisas.
* Correção: quando linhas em branco eram inseridas pelo wp-load ou um de seus arquivos/plugins, a imagem dinâmica dos ícones das formas de pagamento não eram exibidas corretamente.
* Correção: ao desativar o plugin um modal de feedback é exibido. No entanto, se a pessoa mudasse de ideia e clicasse em Cancelar, nada acontecia.

= 4.9.2 =
* Correção: ícones muito grandes em alguns temas
* Correção: ao clicar em "Configurar" na lista de pagamentos (ao invés de ir em PagBank > Configurações) nenhuma alteração feita era salva.

= 4.9.1 =
* Correção: quando exibir meios de pagamento de forma separada estava ativada, os meios de pagamento apareciam mesmo quando desativados.

= 4.9.0 =
* Agora é possível exibir os meios de pagamento de forma individual (ideal para caso você aceite outros meios de pagamento além do PagBank)
* Agora quando um carrinho for recorrente (contiver produtos recorrentes PagBank), somente os meios PagBank suportados serão exibidos. 

= 4.8.0 =
* Agora é possível exibir informações de parcelamento na página do produto em 3 formatos diferentes

= 4.7.2 =
* Melhoria: agora ao desativar o plugin damos a opção de você adicionar um comentário com mais detalhes sobre o motivo da desativação.
* Melhoria: agora exibimos um aviso no admin caso o checkout em blocos esteja em uso.

= 4.7.1 =
* Melhorias gerais no JavaScript do plugin
* Melhoria: adicionado métodos de criptografia no Helper do plugin. Por enquanto usado somente em um caso específico na página de pagamento avulso (order-pay).
* Correção: cálculo de parcelas poderia falhar em alguns cenários quando usado em modo Sandbox, exibindo uma opção com mensagem "undefined...".
* Correção: ao fazer um pedido com pix ou boleto e navegar até a área de meus pedidos, o cliente pode clicar em Pagar e escolher outro meio de pagamento. No entanto, pagamento com cartão e 3Ds não funcionava nesta página. Refizemos algumas coisas para tornar isso possível. Isso também traz compatibilidade a plugins de autorização de pedido (como Order Approval for Woocommerce). Reportado por Tiago da Tikovolpe.
* Melhoria: instalação via composer agora sugere que habilite extensão openssl do PHP, a fim de ter melhor criptografia. No momento a criptografia só é usada para o número do pedido, na página de pagamento avulso (/order-pay).
* Melhorias: code standards

= 4.7.0 =
* Melhoria: cliente passa a receber e-mail informando que o pedido foi cancelado automaticamente após expiração do PIX. Sugerido por Fellipe (The Growth Space). 
* Melhoria: agora é possível excluir o valor frete ao dar desconto em boleto e pix, aplicando o desconto somente aos produtos. Sugerido por Fabio (Kaizen Digital). 

= 4.6.3 =
* Melhoria/Correção: quando determinada configuracao do php era realizada, os ícones dos meios de pagamento não eram exibidos. Reportado por Daniel Carvalho.

= 4.6.2 =
* Adicionado suporte ao WooCommerce 6.5
* Adicionado dependencia do plugin Brazilian Market on WooCommerce, evitando erros de campos obrigatórios em algumas lojas
* Pequenas melhorias de segurança (wp standards compliance)

= 4.6.1 =
* Correção: quando o cliente digitava um cartão inválido ou incompleto e tentava finalizar o pedido, ocasionando falha na criptografia do cartão, o pedido ainda era submetido para o backend e gerando outros erros desnecessários, especialmente se outros campos também estivessem errados ou faltando. O mais clássico era o erro 40002 de encrypted_card.id incorreto.
* Correção: ao desativar e ativar novamente o plugin, um erro de SQL era gerado internamente devido a um bug no WordPress e uma mensagem de que o plugin teria gerado cerca de 1400 caracteres de saída inesperada durante a ativação era exibida no backend.

= 4.6.0 =
* Pedidos PIX agora são cancelados automaticamente após periodo de expiração se não forem pagos
* Em alguns checkouts com plugins personalizados (ex: cartflows) o CSS de nosso plugin não era inserido corretamente, ocasionando quebra de layout e ignorando as cores dos ícones configuradas.
* Ao desativar nosso plugin, agora exibimos uma pergunta sobre o motivo da desativação, para que possamos melhorar o plugin.

= 4.5.0 =
* Adicionado suporte a HPOS (High-Performance Order Storage) para ambientes com este recurso ativado.
* Correção de problemas relacionados aos campos de endereço quando HPOS está ativo.
* Correção em erro 40002 quando o campo complemento de endereço não foi preenchido

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
