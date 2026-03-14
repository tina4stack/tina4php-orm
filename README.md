# tina4php-orm

Object-relational mapping module for the Tina4 PHP framework, providing a simple ORM that works with any Tina4 database driver.

## Installation

```bash
composer require tina4stack/tina4php-orm
```

## Requirements

- PHP >= 8.1

## Usage

### Defining a Model

```php
use Tina4\ORM;

class User extends ORM
{
    public $primaryKey = "id";
    public $tableName = "users";

    public $id;
    public $firstName;
    public $lastName;
    public $email;
}
```

### Loading and Saving Records

```php
// Load a user by primary key
$user = (new User());
$user->load("id = 1");
echo $user->firstName;

// Create a new user
$user = new User();
$user->firstName = "Alice";
$user->lastName = "Smith";
$user->email = "alice@example.com";
$user->save();
```

### Querying Multiple Records

```php
$users = (new User())->select("*", 10)
    ->filter("active = 1")
    ->orderBy("last_name")
    ->asArray();
```

## Testing

```bash
composer test
```

## License

MIT - see [LICENSE](LICENSE)

---

## Our Sponsors

**Sponsored with 🩵 by Code Infinity**

[<img src="https://codeinfinity.co.za/wp-content/uploads/2025/09/c8e-logo-github.png" alt="Code Infinity" width="100">](https://codeinfinity.co.za/about-open-source-policy?utm_source=github&utm_medium=website&utm_campaign=opensource_campaign&utm_id=opensource)

*Supporting open source communities <span style="color: #1DC7DE;">•</span> Innovate <span style="color: #1DC7DE;">•</span> Code <span style="color: #1DC7DE;">•</span> Empower*
