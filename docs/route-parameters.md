# Route parameters & patterns

Route paths can contain **parameters**. A parameter compiles to a capturing
group, and whatever it captures is passed to your handler (see
[Dependency injection](./dependency-injection.md)).

There are two parameter syntaxes, and they behave differently.

## `{name}` — match any segment

A `{name}` placeholder matches **any single non-slash segment**. The name is
just a label; it does **not** constrain the value.

```php
$router->get('/users/{id}', function ($id) {
    return 'User ' . $id; // $id is whatever was in that segment: "42", "abc", ...
});
```

`/users/42` and `/users/abc` both match. Use this when you don't need a
constraint, or when you'll validate the value yourself.

## `:type` — match a typed segment

A `:type` placeholder constrains what it matches. `:id` only matches digits, so
`/users/:id` matches `/users/42` but **not** `/users/abc`.

```php
$router->get('/users/:id', function ($id) {
    return 'User ' . $id; // guaranteed to be digits
});
```

### Built-in types

| Pattern | Matches |
|---|---|
| `:any` | any non-slash segment |
| `:id`, `:int` | digits |
| `:number`, `:float` | numbers (incl. sign / decimals) |
| `:bool` | `true`, `false`, `1`, `0` |
| `:string`, `:slug` | word characters, `-` and `_` |
| `:uuid` | a UUID |
| `:date` | `YYYY-MM-DD` |
| `:locale` | `en` or `en-US` / `en_US` |
| `:everything` | everything, including slashes |

> The `{name}` form of these (e.g. `{id}`) does **not** apply the constraint —
> it always matches any non-slash segment. Use the `:type` form to constrain.

To reuse the same type more than once in a path, append a digit: `:id`, `:id2`,
`:id3` all match digits.

```php
$router->get('/teams/:id/members/:id2', function ($teamId, $memberId) {
    return $teamId . ' / ' . $memberId;
});
```

## Custom patterns: `where()` / `pattern()`

Register your own named pattern. `where()` is an alias of `pattern()`.

```php
$router->where('hex', '[0-9a-f]+');
$router->get('/color/:hex', function ($hex) {
    return '#' . $hex;
});
```

`/color/abc123` matches; `/color/zzz` does not. The pattern is wrapped in a
capturing group automatically if it isn't already.

Notes:

- Reference a custom pattern with the **`:name`** form (`:hex`). The `{name}`
  form is always "any non-slash segment".
- Choose a name that doesn't collide with a built-in type, so the built-in rule
  doesn't take precedence.

## Optional parameters

A trailing `?` makes the last segment optional.

```php
$router->get('/admin/{slug}?', 'AdminController@show')->name('admin');
```

This matches both `/admin/` and `/admin/dashboard`. When generating a URL for a
route with an optional parameter, unused optional segments are stripped — see
[Named routes & URLs](./named-routes.md).

Next: [Route groups](./groups.md).
