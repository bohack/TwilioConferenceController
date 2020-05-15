<!DOCTYPE html>
<html lang="en">
<head>
  <title>Conference Controller</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
  <script>
  function reload() {
  location.replace(location.href);
  }
  setTimeout(reload, 60000);

  function submit(params,caller = '') {
    var data = params.split(",");
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
    for (key = 0; key < data.length; key++) {
      const formInput = document.createElement('input');
      formInput.type = 'hidden';
      formInput.name = key;
      formInput.value = data[key];
      form.appendChild(formInput);
    }
    document.body.appendChild(form);
    form.submit();
  }
  </script>
</head>
<body>
<div class="container">
  <h2 class="text-center">Current Conference Calls</h2>
  <p class="text-center">On the conference dial-in</p>
 <table class="table">
<?php
require_once 'vendor/autoload.php';
require_once 'env.php';

$dbg = False;
if ($dbg) { print_r ($_POST); }
use Twilio\Rest\Client;

$twilio = new Client($sid, $token);

if ($_SERVER['REQUEST_METHOD']=='POST') {
  if (isset($_POST['callNum']) && !empty($_POST['callNum'])) {
    $callAction = 'call';
    $confSid = $_POST['confSid'];
    $partSid = '+1'.$_POST['callNum'];
  } else {
    $callAction = $_POST[0];
    $confSid = $_POST[1];
    $partSid = $_POST[2];
    $featState = $_POST[3];
  }
  switch ($callAction) {
    case 'mute':
      if ($featState) { $featState = 'False'; } else { $featState = 'True'; }
      $participant = $twilio->conferences($confSid)
                            ->participants($partSid)
                            ->update(["muted" => $featState]);
      break;
    case 'kick':
      $twilio->conferences($confSid)
             ->participants($partSid)
             ->delete();
      break;
    case 'hold':
      if ($featState) { $featState = 'False'; } else { $featState = 'True'; }
      $participant = $twilio->conferences($confSid)
                            ->participants($partSid)
                            ->update(["hold" => $featState]);
      break;
    case 'call';
      $call = $twilio->calls
                     ->create($partSid,
                              $bridgeNum,
                              [ "twiml" => "<Response><Dial><Conference>".$confSid."</Conference></Dial></Response>" ]);
      break;
  }
}

$conferences = $twilio->conferences
                      ->read([
                             "status" => "in-progress"
                             ], 20);
foreach ($conferences as $conf) {
?>
    <thead>
      <tr class="table-secondary">
        <th class="text-center"><h4><?php print "Room - ".($conf->friendlyName);  if ($dbg) { print " ".($conf->sid); } ?></h4></th>
        <th>
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
           <div class="input-group-sm d-flex flex-wrap align-content-end">
               <input type="text" class="form-control col-md-3" placeholder="Phone Number" name="callNum">
               <input type="hidden" id="confSid" name="confSid" value="<?php print ($conf->friendlyName); ?>">
               <button type="submit" id="Call" class="btn btn-secondary btn-sm" value="call" onClick="submit">Call</button>
           </div>
          </form>
        </th>
      </tr>
    </thead>
    <tbody>
<?php
    $participants =  $twilio->conferences($conf->sid)
                       ->participants
                       ->read([], 30);
    foreach ($participants as $part) {
       $call = $twilio->calls($part->callSid)
                        ->fetch();
?>
      <tr>
        <td class="text-center"><h5><?php if (($call->from)==$bridgeNum) { print ($call->to); } else { print ($call->from); } if ($dbg) { print " ".($part->callSid); } ?></h5></td>
        <td>
          <div class="btn-group btn-group-sm">
            <button type="button" id="mute" <?php if ($part->muted) { print 'class="btn btn-danger"'; } else { print 'class="btn btn-primary"'; } ?> value="mute,<?php print($conf->sid).','.($part->callSid); if ($part->muted) { print ',1'; } else { print ',0'; } ?>" onClick="submit(this.value)">Mute</button>
            <button type="button" id="kick" class="btn btn-primary" value="kick,<?php print($conf->sid).','.($part->callSid).',0'; ?>" onClick="submit(this.value)">Kick</button>
            <button type="button" id="hold" <?php if ($part->hold) { print 'class="btn btn-danger"'; } else { print 'class="btn btn-primary"'; } ?> value="hold,<?php print($conf->sid).','.($part->callSid); if ($part->hold) { print ',1'; } else { print ',0'; } ?>" onClick="submit(this.value)">Hold</button>
          </div>
        </td>
      </tr>
<?php
       }
}
?>
    </tbody>
 </table>
</div>
</body>
</html>

