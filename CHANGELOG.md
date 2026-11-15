# Alterações

## 0.2.0 (por publicar)

- PDF: formatos `a5` (o A4 reduzido a meia folha), `thermal80` (rolo de 80 mm, igual a `thermal`) e `thermal58` (rolo de 58 mm).
- Venda direta paga com vários meios: `payment => ['payments' => [...]]` (até 6 meios; o troco sai do numerário); o detalhe do documento traz `paymentMethod` (`mixed`),
  `payments` e `amountReceived`.
- Dados bancários: `$brisa->bank()` e `$brisa->setBank()`; `bankDetails` e `printBankDetails` ao emitir `FT` e `PF`; o detalhe traz `bankDetails`.
- Clientes: `list` com `sort`, `desc` e `totals` (e `invoiced`, `amountDue`, `lastInvoiceDate`, `documents` em cada cliente); `statement`, `statementPdf` e `statementCsv`.
- Requer a API de 15 de novembro de 2026 ou posterior; contra uma API anterior, os pedidos antigos continuam a funcionar.

## 0.1.0 (por publicar)

- Primeira versão: documentos (emitir, consultar, PDF, exportar, pagar, recibo global, anular, retificar, notas de crédito, histórico, vencimento,
  envio), clientes, artigos e stock, catálogo (impostos, isenções, categorias), SAF-T, `me()` e `company()`.
- Repetições em `429`/`502`/`503` e falhas de rede com `Retry-After`; `Idempotency-Key` gerado nas criações.
- Exceções por estado (RFC 9457) e verificação de webhooks (`Brisa-Signature`).
- Transporte cURL que usa o armazém de certificados do sistema quando o PHP não tem CAs configurados (Windows).
