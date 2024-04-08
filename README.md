# PagBank Connect (PagSeguro) - Nova Geração
## Com Descontos nas Taxas Oficiais
*Por Ricardo Martins - Parceiro Oficial PagBank desde 2015.*

[![Versão mínima do PHP](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg?style=flat-square)](https://php.net/)
[![Última versão](https://img.shields.io/github/v/release/r-martins/PagBank-WooCommerce)](https://github.com/r-martins/PagBank-WooCommerce)
![Último commit (develop)](https://img.shields.io/github/last-commit/r-martins/PagBank-WooCommerce/develop)
![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/pagbank-connect)
![Downloads por mês](https://img.shields.io/wordpress/plugin/dm/pagbank-connect)
![Avaliação dos clientes no WordPress](https://img.shields.io/wordpress/plugin/stars/pagbank-connect?color=yellow)


Conheça a Nova Geração das nossas integrações com PagBank (v. 4.0+).

**Aceite Pix, Cartão de Crédito e Boleto de forma transparente e economize nas taxas oficiais.**

**Integrado com EnvioFácil (economize até 70% no frete ao usar PagBank)¹**

<details>
  <summary>Veja alguns Screenshots (clique aqui para expandir)</summary>
  <img src="https://i.imgur.com/epgmWWr.jpg" alt="Cartão de Crédito na visão do cliente" title="Cartão de Crédito na visão do cliente"/>
  <img src="https://i.imgur.com/FwTz73C.jpg" alt="PIX - Tela de Sucesso" title="PIX - Tela de Sucesso"/>
  <img src="https://i.imgur.com/wE3YBXX.jpg" alt="Configurações de cartão de crédito" title="Configurações de cartão de crédito"/>
  <img alt="PIX e Boleto - Configurações" src="https://i.imgur.com/nhwMhUO.jpg" title="PIX e Boleto - Configurações"/>
  <img alt="Admin - Tela do Pedido" src="https://i.imgur.com/CIgTLnv.jpg" title="Admin - Tela do Pedido"/>
  <img alt="Envio Fácil" src="https://i.imgur.com/nQlOBfx.jpg" title="Envio Fácil"/>  
  <img alt="3D Secure" src="https://i.imgur.com/hqhgWfM.jpg" title="3D Secure"/>  
  <img alt="Venda Recorrente com Woo" src="https://imgur.com/7pQNwkv.jpg" title="Pedidos Recorrentes"/>  
</details>

# Descrição

<a href="https://www.youtube.com/watch?v=L9Oans5dZ7M"><img src="https://i.imgur.com/nyrybNq.jpg"/></a>

Esta é a forma mais fácil de integrar sua loja com PagBank (PagSeguro).
Ao instalar e configurar nossa integração, você pode aceitar Pix, Boleto e Cartão de Crédito com o meio de pagamento mais confiado pelos brasileiros.

Criado por Ricardo Martins, esta é a 4ª geração das integrações PagSeguro, disponibilizadas desde 2014 no Magento, e desde 2019 no WooCommerce. Mais de 20 mil lojas atendidas e mais de 200 milhões de reais transacionados em nossas integrações.

# Instalação

## WP-CLI (mais fácil e rápido)
Você pode instalar o plugin usando o [WP-CLI](https://wp-cli.org/).
* Basta rodar o comando `wp plugin install pagbank-connect --activate`. Adicione `--allow-root` se estiver rodando o comando como root.
* Repita o processo para instalar o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) com o comando `wp plugin install woocommerce-extra-checkout-fields-for-brazil --activate` caso ainda não tenha ele instalado.

## Instalação manual
* Baixe o [arquivo zip](https://github.com/r-martins/PagBank-WooCommerce/archive/refs/heads/master.zip)
* Crie um diretorio em wp-content/plugins chamado rm-pagbank
* Descompacte o conteúdo do arquivo no diretório criado
* Navegue até Plugins > Plugins instalados, e ative o plugin PagBank Connect
* Instale o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

## Instalação automática
* Navegue até Plugins > Adicionar Novo e procure por \"PagBank Ricardo Martins\"
* Clique no botão para instalar e ative o plugin
* Repita o processo buscando e instalando o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

# Configuração
* Ative o meio de pagamento navegando até WooCommerce > Configurações > Pagamentos, e ativando o PagBank Connect
* Clique no PagBank Connect para acessar as configurações do módulo
* Clique em \"Obter Credenciais\". Você será levado para nosso site, onde poderá escolher o modelo de recebimento (14 ou 30 dias) e então autorizar nossa aplicação.
* Ao clicar no modelo de recebimento desejado, você será levado para o site do PagBank, onde deverá se logar com sua conta e autorizar nossa aplicação.
* Em seguida, será levado(a) de volta para nosso site, onde deverá preencher as informações do responsável técnico por sua loja.
* Feito isso, sua *Connect Key* será exibida e enviada para o e-mail informado. Use ela nas configurações da sua loja.
* Salve as configurações e você está pronto para vender.
* Se desejar, configure opções de parcelamento, e validade do boleto e código pix de acordo com suas necessidades.

# Pré-requisitos

* Ter WooCommerce 4.0 ou superior
* PHP 7.4 ou superior
* Ter uma conta Vendedor ou Empresarial no PagSeguro/PagBank (e obter a sua Connect Key)
* [Autorizar nossa integração](https://pagseguro.ricardomartins.net.br/connect/autorizar.html) em sua conta PagBank.
* Ter instalado o plugin [Brazilian Market on WooCommerce](https://br.wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/) a fim de habilitar campos adicionais de endereço e CPF, que são obrigatórios no PagBank.

# Perguntas Frequentes (FAQ)

## Como funcionam os descontos nas taxas?

Ao usar nossas integrações no modelo de recebimento em 14 ou 30 dias, ao invés de pagar 4,99% ou 3,99%, você pagará cerca de 0,60% a menos e estará isento da taxa de R$0,40 por transação.

Taxas menores são aplicadas para transações parceladas, PIX e Boleto.

Consulte mais sobre elas no nosso site.

## Eu tenho uma taxa ou condição negociada menor que estas. O que faço?

Ao usar nossa integração, nossas taxas e condições serão aplicadas ao invés das suas. Isto é, nas transações realizadas com nosso plugin.

É importante notar que taxas negociadas no mundo físico (moderninhas) não são aplicadas no mundo online.

Se mesmo assim você possuir uma taxa ou condição melhor, e se compromete a faturar mais de R$20 mil / mês (pedidos aprovados usando nossa integração), podemos incluir sua loja em uma aplicação especial. Basta selecionar o modelo "Minhas taxas" quando obter sua Connect Key.


## Tenho outra pergunta não listada aqui

Consulte nossa [Central de ajuda](https://pagsegurotransparente.zendesk.com/hc/pt-br/) e [entre em contato](https://pagsegurotransparente.zendesk.com/hc/pt-br/requests/new) conosco se não encontrar sua dúvida respondida por lá.

A maioria das dúvidas estão respondidas lá. As outras são respondidas em até 2 dias após entrar em contato.

## O plugin atualiza os status automaticamente?

Sim. 

E quando há uma transação no PagBank, um link para ela é exibida na página do pedido. Assim você pode confirmar novamente o status do mesmo.

## Como posso testar usando a Sandbox?

Basta clicar no botão 'Obter Connect Key para Testes' localizado nas configurações do plugin, seguir as instruções, e informar sua Connect Key de testes no campo indicado.

Um link para mais detalhes sobre como utilizar a Sandbox está disponível na página de configurações do plugin.

A equipe do PagBank está trabalhando numa correção.

Enquanto isso, você pode testar com dados reais e realizar o estorno. As tarifas e taxas são reembolsadas, não incidindo nenhum custo.

## Este é um plugin oficial?

Não. Este é um plugin desenvolvido por Ricardo Martins, assim como outros para Magento e WooCommerce desenvolvidos no passado.

Apesar da parceria entre o desenvolvedor e o PagBank que concede descontos e benefícios, este NÃO é um produto oficial.

PagSeguro e PagBank são marcas do UOL.


## Posso modificar e comercializar este plugin?

O plugin é licenciado sob GPL v3. Você pode modificar e distribuir, contanto que suas melhorias e correções sejam contribuidas de volta com o projeto.

Você deve fazer isso através de Pull Requests ao [repositório oficial no github](https://github.com/r-martins/PagBank-WooCommerce).

# Garantia

Conhecido como "software livre", este plugin é distribuido sem garantias de qualquer tipo.

O desenvolvedor ou PagBank não se responsabilizam por quaisquer danos causados pelo uso (ou mal uso) deste plugin.

Esta é uma iniciativa pessoal, sem vínculo com PagBank. PagBank é uma marca do UOL.

Este não é um produto oficial do PagBank.

Ao usar este plugin você concorda com os [Termos de Uso e Política de Privacidade](https://pagseguro.ricardomartins.net.br/terms.html).

---
¹ A Integração com Envio Fácil está disponível apenas para lojas com integração 14 ou 30 dias. Embora utilizemos as APIs do PagSeguro para isso, eles não encorajam o uso dessas APIs. Use com cautela. O PagSeguro não oferece suporte para esta integração.

