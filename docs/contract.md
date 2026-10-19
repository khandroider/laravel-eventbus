# Message contract

Any service, in any language, can publish to and consume from the bus using this format.

## Transport

* Broker: RabbitMQ, AMQP 0-9-1.
* Exchange: `events` (configurable), type `topic`, durable.
* Routing key: `{source}.{type}`, e.g. `hr.candidate_created`, where `source` is the producing project (channel).
  Subscribe with patterns: `hr.candidate_*` is **not** valid AMQP (wildcards match whole dot-separated words), so use
  `hr.*` for every event of one project, `#` for everything, or exact keys like `hr.candidate_created`.
* Each consumer owns a durable queue bound to the exchange. RabbitMQ delivers a copy to every bound queue;
  several instances of one service share one queue.
* Messages are persistent (`delivery_mode=2`), `content_type=application/json`,
  AMQP `message_id` equals the envelope `id`.

## Envelope

```json
{
  "version": 1,
  "id": "018f3c2e-7f1a-7b3a-9c1d-2f4e5a6b7c8d",
  "type": "candidate_created",
  "source": "hr",
  "occurred_at": "2026-10-19T10:00:00.000000Z",
  "payload": {
    "candidateId": "8f0a...",
    "createdAt": "2026-10-19T10:00:00.000000Z",
    "source": "site",
    "tags": ["senior", "php"]
  }
}
```

| Field         | Type   | Rules |
|---------------|--------|-------|
| `version`     | int    | Envelope version, currently `1`. |
| `id`          | string | UUID. Consumers deduplicate by it: the same `id` must never be processed twice. |
| `type`        | string | Event name, `snake_case`, no dots. Stable across versions of the producer. |
| `source`      | string | Producing project / channel, `snake_case`; also the first segment of the routing key. |
| `occurred_at` | string | ISO-8601 in UTC with `Z`. |
| `payload`     | object | Event data. Keys as the producer names them (camelCase in PHP producers). |

### Payload value conventions

* Dates/times: ISO-8601 UTC strings (`2026-10-19T10:00:00.000000Z`).
* Identifiers: strings (UUIDs or stringified ids), never bare database integers unless they are the public id.
* Enumerations: their string/int value, not the name.
* Money: integer minor units plus a currency code, or a decimal string. Never floats.
* Nested objects and arrays are allowed; keep them flat where possible.
* Add fields freely; never rename or change the meaning of an existing one. Breaking changes get a new `type`.

## Delivery semantics

* At-least-once. Producers may redeliver after a crash; always deduplicate by `id`.
* Ordering is guaranteed only within one producer's relay and one routing key; do not rely on cross-type order.
* Consumers should `ack` only after the message is safely stored or processed; on an infrastructure failure `nack`
  with requeue.

## Python example (pika)

```python
import json, pika

conn = pika.BlockingConnection(pika.URLParameters("amqp://guest:guest@rabbitmq:5672/%2F"))
ch = conn.channel()
ch.exchange_declare("events", exchange_type="topic", durable=True)
ch.queue_declare("crm.inbox", durable=True)
ch.queue_bind("crm.inbox", "events", routing_key="hr.*")

def on_message(ch, method, props, body):
    msg = json.loads(body)
    if already_seen(msg["id"]):          # your dedup store
        ch.basic_ack(method.delivery_tag); return
    handle(msg["type"], msg["payload"])
    ch.basic_ack(method.delivery_tag)

ch.basic_qos(prefetch_count=10)
ch.basic_consume("crm.inbox", on_message)
ch.start_consuming()
```

Publishing from Python uses the same envelope with `source: "crm"` and routing key `crm.{type}`;
the Laravel side maps `type` to an event class in `config/eventbus.php` → `inbox.events`.
