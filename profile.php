<?php

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'helpers.php';
require_once 'session.php';

$redis = new Predis\Client($config['redis']);

if (!empty($_GET['code'])) {
    $login_params = array(
        'client_id'     => $config['twitch_client_id'],
        'client_secret' => $config['twitch_client_secret'],
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => $config['twitch_redirect_uri'],
        'code'          => $_GET['code']
    );
    $result = post_url_contents("https://api.twitch.tv/kraken/oauth2/token", $login_params);
    $access_result = json_decode($result);
    if (isset($access_result->access_token)) {
        $token = $access_result->access_token;
        $user_url = 'https://api.twitch.tv/kraken/user?oauth_token='.$token;
        $result = get_url_contents($user_url);
        $user_result = json_decode($result);
        if (isset($user_result->name) && isset($user_result->_id)) {
            $user = array('id' => $user_result->_id, 'name' => $user_result->name);
            $redis->hmset('user:'.$user_result->name, $user);
            $_SESSION['user'] = $user;

            # store sid in redis
            $sid = session_id();
            $session = array('id' => $sid, 'user_name' => $user['name'],
                'user_id' => $user['id']);
            $skey = 'session:'.$sid;
            $redis->hmset($skey, $session);
            $redis->expire($skey, $SESSION_LIFETIME_SECS);
        }
    }

    # Redirect after login
    header('Location: /profile');
    die();
}

if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $channel = array( 'service' => NULL, 'stream' => NULL );
    $channel = array_merge($channel, $redis->hgetall('channel:'.$user['name']));
} else {
  header('Location: /destinychat');
  die();
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Watch streams and videos with destiny.gg!">
    <link rel="icon" href="favicon.ico">
    <title>OverRustle - Profile for <?php echo $user['name'] ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/overrustle.css" rel="stylesheet">
    <script src="js/jquery-1.11.2.min.js"></script>
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
      })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

      ga('create', 'UA-49711133-1', 'overrustle.com');
      ga('send', 'pageview');
    </script>
  </head>

  <body>
    <?php include 'navbar.php' ?>

    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-6">
            <h1 align="center" style="color: white;">Profile for: <?php echo $user['name'] ?></h1>
                <h3 style="color: white;">Set Channel: &nbsp;
                    <a href="/channel?user=<?php echo $user['name'] ?>">
                        <span class="label label-default">Visit Channel</span>
                    </a>
                </h3>
                <form action="channel" method="post" role="form">
                    <div class="form-group">
                        <label for="channelService" style="color: white;">Service</label>
                        <select id="channelService" name="service" class="form-control">
                        <?php
                            foreach ($SERVICE_OPTIONS as $key => $value) {
                                echo '<option value="'.$key.'"'.($channel['service'] == $key ? ' selected' : '').'>'.$value.'</option>';
                            }
                        ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="channelStream" style="color: white;">Stream</label>
                        <input id="channelStream" type="text" name="stream" class="form-control" placeholder="Stream/Video ID"
                            value="<?php echo $channel['stream'] ?>" />
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
        </div>
        <div class="col-sm-3"></div>
      </div>
    </div>

  <script src="js/bootstrap.min.js"></script>
  <script src="js/overrustle.js"></script>
  </body>
</html>
