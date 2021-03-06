<?php
require __DIR__ . '/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \PHPMailer\PHPMailer\PHPMailer as PHPMailer;

class Entry extends \Illuminate\Database\Eloquent\Model {
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $table = 'entry';
    protected $dates = ['deleted_at'];
    protected $fillable = ['category', 'incident', 'who', 'msg'];
}

class Category extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'category';
    public $timestamps = false;
    protected $primaryKey = 'id'; // or null
    public $incrementing = false;
}

# use DotEnv to load a .env file 
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required(['DB_PATH', 'ADMIN_PASSWORD', 'MAIL_FROM', 'APP_NAME']);

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$config['dbpath']   = getenv("DB_PATH") ?  getenv("DB_PATH") : "../dev.sqlite";
$config['mail_from'] = getenv("MAIL_FROM");
$config['app_name'] = getenv("APP_NAME");

$app = new \Slim\App(["settings" => $config]);

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => ["/setup", "/list"],
    "realm" => "Protected",
    "secure" => false,
    "users" => [
        "admin" => "fantail"
    ]
]));

$container = $app->getContainer();
$container['view'] = new \Slim\Views\PhpRenderer(__DIR__ . "/templates/", ['appName' => $config['app_name']]);

$container['pdo'] = function ($c) {
    $pdo = new PDO('sqlite:' + $c['settings']['dbpath']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
};

$container['db'] = function ($container) {
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection([ 'driver' => 'sqlite', 'database' => $container['settings']['dbpath'], 'charset'   => 'utf8', 'collation' => 'utf8_unicode_ci']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    return $capsule;
};

$app->get("/setup", function(Request $request, Response $response){
    $this->get('db')->table('category');
    $response = $this->view->render($response, "categories.phtml", [ 'categories' => Category::all() ]);
});

$app->post("/setup", function(Request $request, Response $response){
    $this->get('db')->table('category');
    $categories = Category::all();
    $data = $request->getParsedBody();

    # update existing
    foreach($categories as $category){

        $myid = $category->id;

        $my_id = trim(filter_var($data['id_'.$myid], FILTER_SANITIZE_STRING));
        $my_name = trim(filter_var($data['name_'.$myid], FILTER_SANITIZE_STRING));
        $my_email = trim(filter_var($data['email_'.$myid], FILTER_SANITIZE_STRING));

        if(!empty($my_id) && empty($my_name) && empty($my_email)){
            
            $category->delete();
        }
        else if(!empty($my_id) && !empty($my_name) && !empty($my_email)){
            $category->id = $my_id;
            $category->name = $my_name;
            $category->email = $my_email;
            $category->save();
        }

    }


    # new row

    $new_id = trim(filter_var($data['id_new'], FILTER_SANITIZE_STRING));
    $new_name = trim(filter_var($data['name_new'], FILTER_SANITIZE_STRING));
    $new_email = trim(filter_var($data['email_new'], FILTER_SANITIZE_STRING));

    if(!empty($new_id) && !empty($new_name) && !empty($new_email))
    {
        $new_cat = new Category;
        $new_cat->id = $new_id;
        $new_cat->name = $new_name;
        $new_cat->email = $new_email;
        $new_cat->save();
    }
    
    return $response->withStatus(302)->withHeader('Location', $request->getUri()->getBasePath() . '/setup');
    
});

$app->get("/", function (Request $request, Response $response) {
    $this->get('db')->table('category');
    $categories = Category::all();
    $response = $this->view->render($response, "index.phtml", ['categories' => $categories]);
});

$app->post("/", function (Request $request, Response $response) {
    $this->get('db')->table('category');
    $categories = Category::all();

    $this->get('db')->table('entry');

    $data = $request->getParsedBody();

    $who = trim($data['who']);
    if($who == null || $who == ''){ $who = 'Anon'; }
    $inc = trim($data['inc']);
    if($inc == null || $inc == ''){ $inc = 'Not Provided'; }

    $mail_from = $this->get('settings')['mail_from'];
    $app_name = $this->get('settings')['app_name'];

    foreach($categories as $category){
        $log_msg = trim($data[$category->id]);

        if( $log_msg != null && $log_msg != ''){
            $entry = Entry::create(['category' => $category->id, 'incident' => $inc, 'who' => $who, 'msg' => $log_msg]);
            $entry->save();

            $mail = new PHPMailer(true);
            $mail->setFrom($mail_from, $app_name);
            $mail->addAddress($category->email);
            $mail->addReplyTo($mail_from); 

            $mail->Subject = $app_name . ' Submission';
            $mail->Body    = 'New submission for: ' . $app_name .' - ' . $category->name . "\n\n   submitted by: " . $who . "\n   incident: " . $inc . "\n\n Message:\n" . $log_msg;

            $mail->send();

        }
    }

    $response = $this->view->render($response, "index.phtml", ['success' => 'Submitted OK!', 'categories' => $categories]);
});

$app->get('/list/{category}', function (Request $request, Response $response) {

    $this->get('db')->table('entry');

    $category = $request->getAttribute('category');

    $entries = Entry::where('category', $category)->orderBy('created_at', 'desc')->get();

    $response = $this->view->render($response, "list.phtml", ["entries" => $entries]);

    return $response;
});
$app->run();
