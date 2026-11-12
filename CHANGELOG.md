# Alterações

## 0.1.0 (por publicar)

- Primeira versão: documentos (emitir, consultar, PDF, exportar, pagar, recibo global, anular, retificar, notas de crédito, histórico, vencimento,
  envio), clientes, artigos e stock, catálogo (impostos, isenções, categorias), SAF-T, `me()` e `company()`.
- Repetições em `429`/`502`/`503` e falhas de rede com `Retry-After`; `Idempotency-Key` gerado nas criações.
- Exceções por estado (RFC 9457) e verificação de webhooks (`Brisa-Signature`).
- Transporte cURL que usa o armazém de certificados do sistema quando o PHP não tem CAs configurados (Windows).
