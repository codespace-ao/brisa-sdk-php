# Política de segurança

## Versões com suporte

Corrigimos falhas de segurança na versão **mais recente**. Atualize sempre que sair uma nova.

## Como reportar uma falha

**Não abra uma issue pública.** Use uma destas vias:

* O botão **Report a vulnerability** no separador *Security* deste repositório (reporte privado do GitHub).
* Ou escreva para **geral@codespace.ao** com o assunto «Segurança SDK PHP».

Diga o que encontrou, como reproduzir, e a versão afetada. Responderemos em **3 dias úteis** e mantê-lo-emos a par até haver correção. Pedimos que nos dê um prazo razoável antes de divulgar; creditamos quem reporta, se quiser.

## Boas práticas para quem usa o SDK

* Use-o no **servidor**; a chave de API nunca vai para o navegador nem para o repositório.
* Guarde a chave como segredo e dê-lhe só as permissões necessárias.
* Verifique **sempre** a assinatura dos webhooks (`Webhook::verify`).
