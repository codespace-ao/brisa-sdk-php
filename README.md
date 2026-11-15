# Brisa para PHP

SDK oficial da API [Brisa](https://brisa.ao): faturação eletrónica certificada pela AGT (Angola).
Emita faturas, recibos e notas de crédito, gira clientes e artigos, exporte o SAF-T e receba webhooks a partir de PHP.

- PHP 8.1 ou mais recente, com `ext-curl` e `ext-json`. Sem outras dependências.
- Repete sozinho os pedidos em `429`, `502`, `503` e falhas de rede, respeitando o `Retry-After`.
- Gera um `Idempotency-Key` nas criações, para que uma repetição nunca emita o mesmo documento duas vezes.
- Erros da API (RFC 9457) como exceções com tipo: `RuleException`, `PlanException`, `ValidationException`...

## Instalação

O pacote está num repositório privado da Codespace. No `composer.json` do seu projeto:

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/codespace-ao/brisa-sdk-php" }],
    "require": { "codespace-ao/brisa-php": "^0.1" }
}
```

```bash
composer update codespace-ao/brisa-php
```

## Começar

```php
use Brisa\Brisa;

$brisa = new Brisa(['apiKey' => getenv('BRISA_API_KEY')]);

$cliente = $brisa->clients->create(['name' => 'Ngola Comercial Lda', 'taxId' => '5000000000', 'email' => 'contas@ngola.ao']);

$fatura = $brisa->documents->issue([
    'type' => 'FT',
    'clientId' => $cliente['id'],
    'lines' => [['productCode' => 'PAR-M8', 'quantity' => 4]],
]);

file_put_contents($fatura['number'] . '.pdf', $brisa->documents->pdf($fatura['id']));
```

A chave escolhe o ambiente: `brk_sbx...` é a sandbox (nada vai à AGT), `brk_...` é produção. O endereço é sempre `https://api.brisa.ao`
(mude-o com `baseUrl` ou `BRISA_API_URL` só para testar contra outro ambiente).

### Opções

| Opção | Por omissão | |
|---|---|---|
| `apiKey` | `BRISA_API_KEY` | A chave de API. |
| `baseUrl` | `BRISA_API_URL` ou `https://api.brisa.ao` | |
| `timeout` | `30` | Segundos de cada tentativa. |
| `maxRetries` | `2` | Repetições em `429`, `502`, `503` e falhas de rede. |
| `maxRetryDelay` | `60` | Espera máxima aceite de um `Retry-After`; acima disso o erro sobe logo. |
| `headers` | `[]` | Cabeçalhos acrescentados a todos os pedidos. |
| `transport` | `new CurlTransport()` | Outro `Brisa\Http\Transport` (ex.: sobre Guzzle). `new CurlTransport('/caminho/cacert.pem')` usa esse ficheiro de CAs. |

Cada método aceita ainda `$options` com `idempotencyKey`, `timeout`, `maxRetries` e `headers` para esse pedido.

## O que há

| | |
|---|---|
| `$brisa->documents` | `issue`, `list`, `listAll`, `get`, `pdf` (`a4`, `a5`, `thermal80`, `thermal58`), `export` (JSON ou XML), `pay`, `payGlobal`, `cancel`, `rectify`, `creditNote`, `credits`, `history`, `setDueDate`, `send` |
| `$brisa->clients` | `create`, `update`, `get`, `list` (com `sort`, `desc`, `totals`), `listAll`, `setActive`, `statement`, `statementPdf`, `statementCsv` |
| `$brisa->products` | `upsert`, `get`, `list`, `listAll`, `moveStock` |
| `$brisa->catalog` | `taxes`, `exemptionReasons`, `categories` |
| `$brisa->saft` | `request`, `list`, `get`, `file`, `waitUntilReady` |
| `$brisa->me()`, `$brisa->company()` | Quem é a chave; os dados da empresa. |
| `$brisa->bank()`, `$brisa->setBank($dados)` | Os dados bancários da empresa (IBAN verificado), que as faturas por pagar imprimem. |
| `$brisa->request($method, $path, ...)` | Qualquer outro endpoint, com autenticação, repetições e erros do SDK. |
| `$brisa->rateLimit()` | Os limites indicados na última resposta (`limit`, `remaining`, `reset`). |

As respostas são arrays associativos, tal como o JSON da API (ver `docs/API.md` e `docs/openapi.json` no repositório da API).
As listas paginadas devolvem `['items' => [...], 'page' => 0, 'size' => 50, 'total' => 123]`; `listAll()` percorre todas as páginas:

```php
foreach ($brisa->documents->listAll(['type' => 'FT', 'from' => '2026-01-01']) as $doc) {
    echo $doc['number'], PHP_EOL;
}
```

### Papel: A4, A5 e rolos térmicos

```php
$a4  = $brisa->documents->pdf($fatura['id']);                              // A4 (por omissão)
$a5  = $brisa->documents->pdf($fatura['id'], ['format' => 'a5']);          // o A4 reduzido a meia folha
$r80 = $brisa->documents->pdf($fr['id'], ['format' => 'thermal80']);       // talão para rolo de 80 mm (FR, TV, RC, RG)
$r58 = $brisa->documents->pdf($fr['id'], ['format' => 'thermal58']);       // talão para rolo de 58 mm
```

### Venda direta paga com vários meios

Num `FR` ou `TV` pode pagar-se parte em numerário, parte no TPA, parte por transferência. O que se paga cobre o total; o **troco** (o que se pagou a mais) sai do
numerário, por isso os meios que não são numerário não podem somar mais do que o total (`RuleException`). O talão e a fatura mostram cada meio, o total pago e o troco.

```php
$fr = $brisa->documents->issue([
    'type' => 'FR',
    'lines' => [['description' => 'Cimento 50 kg', 'quantity' => 1, 'unitPrice' => 100]],
    'payment' => ['payments' => [['method' => 'debit_card', 'amount' => 50], ['method' => 'cash', 'amount' => 100]]], // 114 a pagar: 36 de troco
]);
$d = $brisa->documents->get($fr['id']); // $d['paymentMethod'] === 'mixed', $d['payments'], $d['amountReceived']
```

### Dados bancários nas faturas por pagar

```php
$brisa->setBank(['bankName' => 'BAI', 'holder' => 'Ngola Comercial, Lda', 'iban' => 'AO06 0040 0000 6789 1234 5678 9', 'kwikKey' => '923000000']);
$brisa->documents->issue(['type' => 'FT', 'clientId' => 42, 'lines' => $linhas]);                                  // leva os dados da empresa
$brisa->documents->issue(['type' => 'FT', 'clientId' => 42, 'lines' => $linhas, 'bankDetails' => ['iban' => '...']]); // outros, só nesta fatura
$brisa->documents->issue(['type' => 'FT', 'clientId' => 42, 'lines' => $linhas, 'printBankDetails' => false]);       // nenhuns
```

### Clientes: ordem, totais e extrato

```php
// por quanto se faturou a cada um (`totals` acrescenta invoiced, amountDue, lastInvoiceDate e documents)
$top = $brisa->clients->list(['sort' => 'invoiced:desc', 'totals' => true, 'size' => 10]);

// a conta corrente do cliente, e para imprimir (precisa também de `reports.export`)
$extrato = $brisa->clients->statement($id, ['from' => '2026-11-01', 'to' => '2026-11-30']);
$pdf = $brisa->clients->statementPdf($id);
$csv = $brisa->clients->statementCsv($id);
```

Ordens da lista: `name` (por omissão), `taxId`, `city`, `createdAt` e, que pedem os totais, `invoiced`, `due`, `lastInvoice`, `documents`.

### SAF-T

```php
$export = $brisa->saft->request('2026-01-01', '2026-01-31');
$brisa->saft->waitUntilReady($export['id']);
file_put_contents('saft-2026-01.xml', $brisa->saft->file($export['id']));
```

## Erros

```php
use Brisa\Exception\{RuleException, PlanException, ValidationException, ApiException, ConnectionException};

try {
    $brisa->documents->issue($pedido);
} catch (ValidationException $e) {      // 400: $e->errors() = ['campo' => 'mensagem']
} catch (PlanException $e) {            // 402: $e->metric(), $e->limit()
} catch (RuleException $e) {            // 422: regra fiscal; $e->field() diz qual
} catch (ApiException $e) {             // outros: $e->status(), $e->type() (urn:brisa:...), $e->detail()
} catch (ConnectionException $e) {      // rede ou tempo esgotado (TimeoutException)
}
```

Programe contra `status()`, `type()` e `field()`, não contra o texto de `detail()` (em português, pode mudar).
Uma criação que falhou por rede pode ser repetida com o mesmo `idempotencyKey`: a API devolve o resultado da primeira vez.

## Webhooks

```php
use Brisa\Webhook;
use Brisa\Exception\WebhookSignatureException;

try {
    $event = Webhook::verify(file_get_contents('php://input'), $_SERVER['HTTP_BRISA_SIGNATURE'] ?? null, getenv('BRISA_WEBHOOK_SECRET'));
} catch (WebhookSignatureException $e) {
    http_response_code(400);
    exit;
}
// $event['type'], $event['data']...
http_response_code(200);
```

Use o corpo **bruto**. Durante a troca do segredo pode passar os dois: `Webhook::verify($corpo, $assinatura, [$novo, $anterior])`.
`Webhook::sign($corpo, $segredo)` calcula o cabeçalho, para testar o seu endpoint.

## Desenvolvimento

```bash
composer install
composer lint
composer test
BRISA_API_KEY=brk_sbx... BRISA_API_URL=https://... composer test:integration
```

Licença MIT.
