<?php ?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="favicon.ico" type="image/icon">
  <link rel="stylesheet" href="css/wonitor.css">
</head>

<body>
<?php
  require_once 'dbUtil.php';
  $db = openDB( $wonitorDb );
  if (isset($_GET['serverId'])) {
    $query = 'SELECT serverId, serverName, COUNT(1) as rounds, CAST(AVG(averageSkill) as INT) as averageSkill FROM rounds WHERE serverId = :serverId';
    $statement = $db->prepare($query);
    $statement->bindValue(':serverId', $_GET['serverId']);
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $statement->execute();
    $servers = $statement->fetchAll();
  } else {
    $query = 'SELECT serverId, serverName, COUNT(1) as rounds, CAST(AVG(averageSkill) as INT) as averageSkill FROM rounds GROUP BY serverId ORDER BY count(1) DESC';
    $servers = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
  }
  // add total stats
  if (count($servers)>1) {
    $query = 'SELECT COUNT(1) as rounds, CAST(AVG(averageSkill) as INT) as averageSkill FROM rounds';
    $allServers = $db->query( $query, PDO::FETCH_ASSOC )->fetch();
    $allServers['serverName'] = 'All Servers';
    $servers[] = $allServers;
  }
  closeDB( $db );
  foreach ($servers as $server) {
    $serverConstraint = isset($server['serverId']) ? '&serverId_is='.$server['serverId'] : '';
    $constraints = $serverConstraint;
    if (preg_match('/[Ss]iege/', $server['serverName'])) {
      $constraints .= '&map_mt=sg_*' . '&numPlayers_gt=4';
    } else {
      $constraints .= '&map_mt=ns2_*' . '&numPlayers_gt=6';
    }
?>
  <h2><?php
    if(isset($server['serverId']))
      echo '<a href="./index.php?serverId='.$server['serverId'].'" target="_self">';
      echo $server['serverName'];
   if(isset($server['serverId']))
     echo '</a>';
   ?> <span>(<?php echo $server['rounds'];?> rounds on record, average skill per round: <?php echo $server['averageSkill'];?>)</span></h2>
  <div class="container">
    <div class="panel col1">
      <span>Team Balance</span>
      <font size="2">
      <div plotSpecs="#x=winner&y=count<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col1">
      <span>Map Ranking (Rounds)</span>
      <font size="1">
      <div plotSpecs="#x=map&y=numRounds&plotType=pie&textInfo=text<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col1">
      <span>Map Ranking (Hours)</span>
      <font size="1">
      <div plotSpecs="#x=map&y=length_sum&yScaleBy=3600&plotType=pie&yLabel=Round Length Sum in Hours&textInfo=text<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col3">
      <span>Team Balance by Map</span>
      <font size="1">
      <div plotSpecs="#x=map&y=count&t=winner&tNormalize&yLabel=Win Ratio<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col2">
      <span>Game Lengths Distribution</span>
      <font size="1">
      <div plotSpecs="#x=length&xBinSize=60&xScaleBy=60&y=numRounds&xLabel=Round Length in Minutes<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col3">
      <span>Game Lengths by Map</span>
      <font size="1">
      <div plotSpecs="#x=length&xBinSize=60&xScaleBy=60&y=map&s=count&t=map&sizeRef=0.12&xLabel=Round Length in Minutes&tSort=none&hideLegend<?php echo $constraints;?>"></div>
      </font>
    </div>
    <div class="panel col3">
      <span>Winner by Start Location</span>
      <font size="1">
      <div plotSpecs="#x=startLocation1&y=startLocation2&s=count&sizeRef=0.2&t=winner<?php echo $constraints;?>"></div>
      </font>
    </div>
  </div>
<?php
  } // foreach server
?>
  <footer>
    <?php if(isset($server['serverId'])) { ?>
    <a class="bigLink" href=".">Show All Servers</a>
    <?php } ?>
    <a class="bigLink" href="configurator.html">Make Your Own</a>
    <?php if (dataBaseExists($ns2plusDb)) { ?>
    <a class="bigLink" href="deathMap.php">Death Map</a>
    <?php } ?>
  </footer>
  <script src="js/d3.min.js" charset="utf-8"></script>
  <script src="js/plotly.min.js" charset="utf-8"></script>
  <script src="js/husl.min.js" charset="utf-8"></script>
  <script src="js/wonitor.js" charset="utf-8"></script>
  <script>
    var panels = xpath('//div[contains(@class,"panel")]');
    for (var i = 0; i < panels.length; i++) {
      var headline = xpath0('./span', panels[i]);
      var plotdiv  = xpath0('.//div[@plotSpecs or @plotspecs]', panels[i]);
      var configLink = document.createElement('a');
      configLink.className = 'configLink';
      configLink.href = 'configurator.html' + plotdiv.getAttribute('plotSpecs');
      configLink.textContent = '⚙';
      panels[i].insertBefore(configLink, headline.nextSibling);
    }
  </script>
  <div id="jsCheck" style="position:fixed;top:0;bottom:0;left:0;right:0;width:100%;height:100%;background:#262b33;background:rgba(0,0,0,0.8);">
    <div style="position:relative;margin:10%;padding:2em;text-align:center;color:#cdcdcd;background:#803C3C;border-radius:16px;border:10px #532727 solid;font-size:32px;">
      Your viewer doesn't (fully) support JavaScript.<br>
      Try to open the page in another browser or enable JavaScript.
    </div>
    <div style="position:absolute;top:15px;right:15px;padding:0.1em;text-align:center;color:#cdcdcd;background:#1A9837;font-size:32px;">
      Please open in Steam <span style="font-size:64px">↗</span>
    </div>
  </div>
  <script>document.getElementById('jsCheck').remove();</script>
</body>

</html>
