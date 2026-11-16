# Contribuir para o SDK PHP da Brisa

Obrigado por querer ajudar. Este guia diz como propor uma mudança e o que esperamos dela.

## Antes de começar

* **Falha ou ideia?** Procure primeiro nas [issues](https://github.com/codespace-ao/brisa-sdk-php/issues); se não existir, abra uma com o modelo certo.
* **Mudança grande?** Abra uma issue antes de escrever código, para combinarmos o caminho. Correções pequenas e de documentação podem ir direto num *pull request*.
* **Falha de segurança?** Não abra uma issue pública: veja [SECURITY.md](SECURITY.md).

## Preparar o ambiente

Precisa de PHP 8.1 ou mais recente com `ext-curl` e `ext-json`, e do Composer.

```bash
git clone https://github.com/codespace-ao/brisa-sdk-php.git
cd brisa-sdk-php
composer install
composer lint && composer test
```

Os testes de integração correm contra a API real e só com uma chave de **sandbox**:

```bash
BRISA_API_KEY=brk_sbx_... composer test:integration
```

Nunca ponha uma chave num commit, numa issue ou num *pull request*.

## Propor uma mudança

1. Faça um *fork* e crie um ramo a partir de `main` (`fix/erro-no-pdf`, `feat/novo-recurso`).
2. Escreva o código **e os testes**: uma correção traz o teste que falhava; um recurso traz testes do que faz e do que recusa.
3. Corra `composer lint && composer test`. O CI faz o mesmo em PHP 8.1 a 8.4.
4. Atualize a documentação em `docs/` e o `CHANGELOG.md`.
5. Abra o *pull request* e preencha o modelo. Explique o **porquê**, não só o quê.

Um *pull request* é aceite quando o CI passa, tem testes e um mantenedor o aprova. Pode demorar alguns dias; se ninguém responder numa semana, volte a pedir.

## Estilo

* `declare(strict_types=1)` em todos os ficheiros e tipos em tudo o que for público.
* Sem dependências além de `ext-curl` e `ext-json` (é uma regra do projeto).
* Mensagens de *commit* no estilo convencional: `fix(pdf): ...`, `feat(clients): ...`, `docs: ...`.
* Texto para pessoas (documentação, mensagens de erro) em português de Angola.

## Compatibilidade

Não quebramos a API pública sem um aviso e uma entrada no [guia de atualização](docs/upgrade.md). Antes da versão 1.0 uma versão menor pode trazer mudanças, mas tentamos evitá-las.

## Código de conduta

Ao participar, aceita o [Código de Conduta](CODE_OF_CONDUCT.md).
