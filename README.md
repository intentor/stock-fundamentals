# Stock Fundamentals Simple API

Gets stock data from [Fundamentus](https://www.fundamentus.com.br/), a traditional Brazilian stock fundamentals website, and returns it through a JSON API.

## Requirements

A host provider with PHP 7+.

## Usage

Upload `stock.php` file to yout host provider and call it through your host's URL passing the stock code, with or without the fraction (`F`) identification:

```
https://<host>/stock.php?stock=<stockCode>
```

### Example

```
https://<host>/stock.php?stock=PETR4
```

```javascript
{
   "stock" : "PETR4",
   "price" : 14.32,
   "iv" : 39.63,
   "sm" : 63.87,
   "eps" : 3.08,
   "bvps" : 22.66,
   "roe" : 13.6,
   "pe" : 4.65,
   "pbv" : 0.63,
   "dy" : 6.6
}
```

### Parameters

* `stock`: Stock code [string];
* `price`: Stock price [float];
* `iv`: Stock Intrinsic Value (the price the stock should actually have), according to [Benjamin Graham’s Formula](https://en.wikipedia.org/wiki/Benjamin_Graham_formula). [float];
* `sm`: Safety Margin [float | percentage];
* `eps`: Earnings Per Share (EPS [EN] | LPA [PT]) [float | value];
* `bvps`: Book Value Per Share (BVP [EN] | VPA [PT]) [float | value];
* `roe`: Return on Equity (ROE [EN/PT]) [float | percentage];
* `pe`: Price/Earnings (P/E [EN] | P/L [PT]) [float | years]
* `pbv`: Price/Book Value Ratio (P/BV [EN] | P/VP [PT]) [float | ratio];
* `dy`: Divident Yield [float | percentage];

### Using an authentication token

At the top of the `stock.php` file, it's possible to add one or more tokens, which can be used to restrict access to the API:

```php
define("AUTH_TOKENS", [
    "my-token"
]);
```

Then, the token can be used when calling the API to authenticate the request:

```
https://<host>/stock.php?token=my-token&stock=<stockCode>
```

## Google Sheets

If you want to use the API in a Google Sheet, it's possible to add a custom script to access the stock fundamentals as a function.

1. Create a new sheet.
2. Go to **Tools**/**Script Editor**.
3. Go to **File**/**New**/**Script file**.
4. Call it e.g. `Stock`.
5. Copy the script below, replacing the API `host` and `token` accordingly:
```javascript
function stock(stockCode, dataId) {
  var response = UrlFetchApp.fetch("https://<host>/stock.php?token=<token>&stock=" + stockCode);
  var json = response.getContentText();
  var data = JSON.parse(json);
  
  if (!dataId) {
   dataId = "price"; 
  }
  
  return data[dataId];
}
```
6. Save the script.
7. Use the function `=stock("<Stock Code>", "<Stock JSON return parameter">)` to return a given API stock parameter to a cell.

## <a id="license"></a>License

Licensed under the [The MIT License (MIT)](http://opensource.org/licenses/MIT). Please see [LICENSE](LICENSE) for more information.