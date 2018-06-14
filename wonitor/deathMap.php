<?php ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="favicon.ico" type="image/icon">
  <link rel="stylesheet" href="css/deathMap.css">
</head>
<body>
<?php
  require_once 'dbUtil.php';
  $db = openDB( $ns2plusDb );
  $query = 'SELECT serverId, name as serverName FROM ServerInfo GROUP BY serverId ORDER BY serverName;';
  $servers = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
  $query = 'SELECT DISTINCT mapName AS map FROM RoundInfo;';
  $maps = $db->query( $query, PDO::FETCH_NUM )->fetchAll(PDO::FETCH_COLUMN, 0);
  $firstMap = $maps[0];
  $query = 'SELECT roundId, roundDate, roundLength, winningTeam FROM RoundInfo WHERE mapName = :mapName;';
  $stmt  = $db->prepare($query);
  $stmt->bindValue(':mapName', $firstMap, PDO::PARAM_STR);
  $stmt->setFetchMode( PDO::FETCH_ASSOC );
  $stmt->execute();
  $rounds = $stmt->fetchAll();
  closeDB( $db );
?>
  <header class="header"></header>
  <div class="middle">
    <div class="mainpanel">
      <div class="mapcontainer">
        <div id="killsTooltip">
          <div id="killsTooltipLine"></div>
          <div id="killsTooltipBlip"></div>
          <div id="killsTooltipMessage"><span class="killerName"></span> <span class="deathIcon"></span> <span class="victimName"></span></div>
        </div>
        <div id="killsOverlay"></div>
        <div id="heatMapOverlay">
          <div id="heatMapContainer"></div>
        </div>
        <div id="map">
          <img />
        </div>
      </div>
      <div id="controls">
        <!--- ▶⏸⏵⏹ ⏯ ⏮⏭ ⏪⏩ -->
        <label id='controlsPlay' class='controlElement'>▶</label>
        <label id='controlsJumpBack' class='controlElement'>⏮</label>
        <input type="range" id="gameTimeSlider" min="0" max="120.4" step ="0.01" />
        <label id='controlsJumpForward' class='controlElement'>⏭</label>
        <span><label id="gameTime">00:00</label>/<label id="maxGameTime">00:00</label></span>
        <span id="controlsSpeed">
          <input type="radio" id="speed1"  value=1  name="controlsSpeed" class="radioGroup" checked /><label for="speed1">1x</label><!-- 
       --><input type="radio" id="speed2"  value=2  name="controlsSpeed" class="radioGroup" /><label for="speed2">2x</label><!-- 
       --><input type="radio" id="speed10" value=10 name="controlsSpeed" class="radioGroup" /><label for="speed10">10x</label><!-- 
       --><input type="radio" id="speed30" value=30 name="controlsSpeed" class="radioGroup" /><label for="speed30">30x</label><!-- 
       --><input type="radio" id="speed60" value=60 name="controlsSpeed" class="radioGroup" /><label for="speed60">60x</label><!-- 
       --><input type="radio" id="speed120" value=120 name="controlsSpeed" class="radioGroup" /><label for="speed120">120x</label>
        </span>
      </div>
    </div>
    <aside class="aside-config" id="configurator">
      <h2>Config</h2>
      <table>
        <tr>
          <td>
          </td>
          <td>
            <img src="images/texts/1.png" alt="1. select a map" class="helpText"/>
          </td>
        </tr>
        <tr>
          <td>Server</td>
          <td><select id="serverSelect">
            <?php
            if (count($servers)>1) {
              echo "<option value=\"\">All Servers</option>";
            }
            foreach ($servers as $server) {
              echo "<option value=\"" . $server['serverId'] . "\">" . $server['serverName'] . "</option>";
            }
            ?>
          </select><span></span></td>
        </tr>
        <tr>
          <td>Map</td>
          <td><select id="mapSelect"></select><span></span></td>
        </tr>
        <tr>
          <td>
          </td>
          <td>
            <img src="images/texts/2.png" alt="1. select one or more rounds" class="helpText"/>
          </td>
        </tr>
        <tr>
          <td>
            Rounds
          </td>
          <td>
            <select id="roundSelect" size="12" multiple></select>
          </td>
        </tr>
        <tr>
          <td>
          </td>
          <td>
            <img src="images/texts/3.png" alt="3. apply filters (optional)" class="helpText"/>
            <button id="queryFiltersHelp" type="button" >?</button>
          </td>
        </tr>
        <tr>
          <td>
            Filter
          </td>
          <td>
            <textarea id="queryFilters" rows="6" cols="27"></textarea>
          </td>
        </tr>
        <tr>
          <td>
            Heatmap
          </td>
          <td>
            <label for="heatMapToggle"><input type="checkbox" id="heatMapToggle"><span></span></label>
          </td>
        </tr>
      </table>
    </aside>
  </div>
  <svg style="height: 0;">
    <filter id="marineBlue" color-interpolation-filters="sRGB" x="0" y="0" height="100%" width="100%">
      <feColorMatrix type="matrix"
      values="0.08 0.08 0.08 0 0
              0.20 0.20 0.20 0 0
              0.34 0.34 0.34 0 0
              0    0    0    1 0" />
              <!--  0.24, 0.60, 1.00 = RGB(61, 153, 255) -->
    </filter>
    <filter id="marineLightBlue" color-interpolation-filters="sRGB" x="0" y="0" height="100%" width="100%">
      <feColorMatrix type="matrix"
      values="0.14 0.14 0.14 0 0
              0.28 0.28 0.28 0 0
              0.34 0.34 0.34 0 0
              0    0    0    1 0" />
              <!--  0.43, 0.83, 1.00 = RGB(110, 213, 255) -->
    </filter>
    <filter id="alienRed" color-interpolation-filters="sRGB" x="0" y="0" height="100%" width="100%">
      <feColorMatrix type="matrix"
      values="0.27 0.27 0.27 0 0
              0.10 0.10 0.10 0 0
              0.03 0.03 0.03 0 0
              0    0    0    1 0" />
              <!--  0.81, 0.30, 0.09 = RGB(206, 77, 23) -->
    </filter>
    <filter id="alienGold" color-interpolation-filters="sRGB" x="0" y="0" height="100%" width="100%">
      <feColorMatrix type="matrix"
      values="0.32 0.32 0.32 0 0
              0.20 0.20 0.20 0 0
              0.08 0.08 0.08 0 0
              0    0    0    1 0" />
              <!--  1.00, 0.60, 0.24 = RGB(255, 153, 61) -->
    </filter>
    <filter id="alienOrange" color-interpolation-filters="sRGB" x="0" y="0" height="100%" width="100%">
      <feColorMatrix type="matrix"
      values="0.30 0.30 0.30 0 0
              0.15 0.15 0.15 0 0
              0.07 0.07 0.07 0 0
              0    0    0    1 0" />
              <!--  0.90, 0.45, 0.21 = RGB(230, 115, 54)  -->
    </filter>
  </svg>
  <footer class="footer"></footer>
  <script src="js/d3.min.js" charset="utf-8"></script>
  <script src="js/heatmap.min.js" charset="utf-8"></script>
  <script src="js/deathMap.js" charset="utf-8"></script>
</body>
</html>
