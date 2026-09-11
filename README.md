# Prostor cumulative discount

Magento Open Source 2.4.9 project with the `Prostor_CumDiscount` module in `src/app/code/Prostor/CumDiscount`.

The module gets an authenticated customer's 90-day spend from a loyalty API and applies the matching discount only to products without `promo_excluded`.

## Requirements

- Docker Engine with Docker Compose
- Magento Marketplace Composer credentials in `../.secrets/composer-auth.json`

The credentials are used only by Composer. They are not part of this repository.

## Start

```bash
cp .env.example .env
./scripts/bootstrap-env.sh
./scripts/install-magento.sh
```

Open `http://localhost:18090` after the installation completes.

## Configuration

Open **Stores → Configuration → Prostor → Cumulative Discount**, select the website scope, and set:

| Setting | Local value |
| --- | --- |
| Enable | Yes |
| Loyalty Service Base URL | `http://loyalty-mock:8080` |
| Loyalty Service Token | Any non-empty local value |
| Request Timeout | `10` |
| Cache TTL | `300` |
| Loyalty Currency | `UAH` |
| Thresholds | `4000 → 3%`, `8000 → 5%` |

The local mock accepts `normal`, `below-threshold`, `http-500`, and `timeout` modes. Change `LOYALTY_MOCK_MODE` in `.env`, recreate the mock, and clean the loyalty cache:

```bash
docker compose --env-file .env -f compose.yaml up -d --force-recreate loyalty-mock
docker compose --env-file .env -f compose.yaml exec app bin/magento cache:clean prostor_loyalty
```

`normal` returns `8123.50 UAH`; `below-threshold` returns `3500.00 UAH`.

## Check the discount

Create two simple products and add both to the cart of an authenticated customer:

- eligible product: price `1000 UAH`, `promo_excluded = No`
- excluded product: price `500 UAH`, `promo_excluded = Yes`

With `normal` mode, the `8000 → 5%` threshold applies. The cart shows `Prostor Cumulative: -50.00 UAH`; the excluded product is not discounted. With `below-threshold`, no cumulative discount is shown. A `500` response or timeout also leaves the cart usable without the discount and writes a warning to `src/var/log/prostor_cumdiscount.log`.

The captured cart is at [docs/screenshots/prostor-cumulative-cart.png](docs/screenshots/prostor-cumulative-cart.png).

## Tests

```bash
docker compose --env-file .env -f compose.yaml exec app vendor/bin/phpunit --no-extensions app/code/Prostor/CumDiscount/Test/Unit
```

## Architecture

`Cumulative` is a quote-address total collector. It calculates the eligible item total and applies the selected percentage as a separate total after catalog price effects. Before calling the loyalty service, the module checks Redis for a valid response for the current customer and website. A successful response is stored for five minutes. API, cache, and validation failures leave the cart without this discount. The module does not modify Magento core.
