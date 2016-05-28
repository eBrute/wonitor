/* global d3, h337*/
var minimapBlipTextureSize = 256; // 8 x 8
var minimapBlipSize = 16;
var minimapBlipBackgroundSize = minimapBlipSize / 32 * minimapBlipTextureSize;
var botName = '[Bot]';
var mapExtents = {
  origin: {
    x: 0,
    y: 0,
    z: 0
  },
  scale: {
    x: 0,
    y: 0,
    z: 0,
    xzMax: 0
  }
};
var deathIconSize = 16;
var playerPlaying = false;
var playerSpeed = 1;
var heatMap = null;
var heatMapConfig = {
  value: 4,
  max: 10,
  radius: 14,
  opacity: 0.4,
  width: 1024,
  height: 1024
};
var lastSliderPos = 0;


function updateKillFeed(responseText) {
  var queryData = JSON.parse(responseText);

  var kills = d3.select('#killsOverlay')
    .selectAll('div')
    .data(queryData);

  kills.enter()
    .append('div');

  kills.exit()
    .remove();

  resetHeatMap();

  drawKillFeed();

  playerStop();

  var maxGameTime = d3.max(queryData, function(d) {
    return d.gameTime;
  }) || 0;

  d3.select('#maxGameTime')
    .text(formatGameTime(maxGameTime));

  d3.select('#gameTimeSlider')
    .attr('max', maxGameTime)
    .property('value', maxGameTime)
    .node()
    .dispatchEvent(new Event('change'));
}


function drawKillFeed() {
  d3.select('#killsOverlay')
    .selectAll('div')
    .each(function(d) {
      d.gameTime = Number(d.gameTime);
      d.wasVisible = false;

      if (d.victimSteamId === null || d.victimSteamId == 0) {
        d.victimName = botName;
      }

      if (d.killerSteamId == 0) {
        d.killerName = botName;
      }

      var angle = -Math.PI / 2;
      if (d.doerPosition || d.killerPosition) {
        angle = getRelationBetweenPoints(CoordinatesToMap(d.victimPosition), CoordinatesToMap(d.doerPosition || d.killerPosition))[0];
      }
      addMinimapBlip(d3.select(this), d.victimClass, getColorFilterForBlipIcon(d.victimClass), d.victimPosition, angle);
    })
    .on('click', showTooltip)
    .on('mouseenter', showTooltip)
    .on('mouseleave', hideTooltip);
}


function repaintKillFeed() {
  var currentTime = Number(d3.select('#gameTimeSlider')
    .node()
    .value);

  var maxTime = Number(d3.select('#gameTimeSlider')
    .node()
    .max);

  d3.select('#gameTime')
    .text(formatGameTime(currentTime));

  var kills = d3.select('#killsOverlay')
    .selectAll('div');

  kills.each(
    function(d) {
      d3.select(this)
        .style({
          display: d.gameTime <= currentTime ? 'block' : 'none',
          opacity: calculateBlipOpacity(d.gameTime, currentTime, maxTime)
        });
    });

  kills.filter(
      function(d) {
        return d.wasVisible === false && d.gameTime <= currentTime;
      })
    .each(
      function(d) {
        d.wasVisible = true;
        heatMap.addData(CoordinatesToHeatMap(d.victimPosition));
      });
}


function calculateBlipOpacity(gameTime, currentTime, maxTime) {
  if (!gameTime) return 1;
  if (!currentTime) {
    currentTime = Number(d3.select('#gameTimeSlider')
      .node()
      .value);
  }
  if (!maxTime) {
    maxTime = Number(d3.select('#gameTimeSlider')
      .node()
      .max);
  }
  if (currentTime == maxTime && !playerPlaying) return 1;

  var minOpacity = 0.4;
  var timeDiff = currentTime - gameTime;
  var dropOffTime = Math.clamp(1, maxTime / 3, 120);
  var opacity = 1 - (1 - minOpacity) * timeDiff / dropOffTime;
  return Math.clamp(minOpacity, opacity, 1);
}


function playerJumpForward() {
  var maxTime = Number(d3.select('#gameTimeSlider')
    .node()
    .max);
  d3.select('#gameTimeSlider')
    .property('value', maxTime)
    .node()
    .dispatchEvent(new Event('change'));
}


function playerJumpBack() {
  d3.select('#gameTimeSlider')
    .property('value', 0)
    .node()
    .dispatchEvent(new Event('change'));
}


function playerStart() {
  var currentTime = Number(d3.select('#gameTimeSlider')
    .node()
    .value);
  var maxTime = Number(d3.select('#gameTimeSlider')
    .node()
    .max);

  if (currentTime == maxTime) {
    playerJumpBack();
  }

  playerPlaying = true;

  d3.select('#controlsPlay')
    .text('⏸');

  setTimeout(playerAdvance, 10);
}


function playerStop() {
  playerPlaying = false;

  d3.select('#controlsPlay')
    .text('▶');
}


function playerStartPause() {
  if (playerPlaying) {
    playerStop();
  } else {
    playerStart();
  }
}


function playerAdvance() {
  if (!playerPlaying) return;

  var currentTime = Number(d3.select('#gameTimeSlider')
    .node()
    .value);
  var maxTime = Number(d3.select('#gameTimeSlider')
    .node()
    .max);

  currentTime = currentTime + 0.01 * playerSpeed;
  if (currentTime >= maxTime) {
    currentTime = maxTime;
    playerStop();
  }

  d3.select('#gameTimeSlider')
    .property('value', currentTime)
    .node()
    .dispatchEvent(new Event('change'));

  setTimeout(playerAdvance, 10);
}


function onSliderAdvance() {
  var currentTime = Number(d3.select('#gameTimeSlider')
    .node()
    .value);

  if (currentTime < lastSliderPos) {
    resetHeatMap();
  }

  lastSliderPos = currentTime;
  repaintKillFeed();
}


function formatGameTime(gameTime) {
  var min = Math.floor(gameTime / 60);
  var sec = Math.floor(gameTime - min * 60);
  return ('0' + min)
    .substr(-2) + ':' + ('0' + sec)
    .substr(-2);
}

/* Tooltip contains
 * killsTooltipMessage
 * killsTooltipBlip
 * killsTooltipLine
 */
function showTooltip(d) {
  var killer = getKiller(d);

  var tooltipMessage = d3.select('#killsTooltipMessage');
  tooltipMessage.select('span.killerName')
    .text(d.victimSteamId != d.killerSteamId ? d.killerName : '');
  tooltipMessage.select('span.victimName')
    .text(d.victimName);
  tooltipMessage.select('span.deathIcon')
    .each(function() {
      addDeathIcon(d3.select(this), d.killerWeapon);
    });
  var tooltipPosition = calculateTooltipPosition(killer.position ? killer.position : d.victimPosition, d.victimPosition);
  tooltipMessage.style({
    left: tooltipPosition[0] + 'px',
    top: tooltipPosition[1] + 'px'
  });

  d3.select('#killsTooltipLine')
    .each(function() {
      if (!killer.position || killer.class == 'Dead' || isSuicide(d)) {
        drawLine(d3.select(this), CoordinatesToMap(d.victimPosition), CoordinatesToMap(d.victimPosition), false); // remove line
      } else {
        drawLine(d3.select(this), CoordinatesToMap(d.victimPosition), CoordinatesToMap(killer.position));
      }
    });

  d3.select('#killsTooltipBlip')
    .each(function() {
      if (!killer.class || !killer.position || killer.class == 'Dead' || isSuicide(d)) {
        addMinimapBlip(d3.select(this), 'None', 'none', '0 0 0', 0);
      } else {
        var angle = getRelationBetweenPoints(CoordinatesToMap(killer.position), CoordinatesToMap(d.victimPosition))[0];
        addMinimapBlip(d3.select(this), killer.class, getColorFilterForBlipIcon(killer.class, true), killer.position, angle);
      }
    });

  d3.select(this)
    .style('opacity', 1);

  d3.select('#killsTooltip')
    .style('opacity', 1);
}


function hideTooltip(d) {
  d3.select('#killsTooltip')
    .style('opacity', 0);

  d3.select(this)
    .style('opacity', calculateBlipOpacity(d.gameTime));
}


function getKiller(d) {
  var killerData = {
    position: d.killerPosition,
    class: d.killerClass,
    name: d.killerName,
    weapon: d.killerWeapon,
    steamId: d.killerSteamId,
    teamNumber: d.killerTeamNumber,
    location: d.killerLocation
  };

  if (d.killerWeapon && (isActiveEntity(d.killerWeapon, d.killerClass) || d.killerClass == 'Commander' && d.doerPosition)) {
    killerData.position = d.doerPosition;
    killerData.location = d.doerLocation;
    killerData.class = d.killerWeapon;
  }
  return killerData;
}


function calculateTooltipPosition(killerCoords, victimCoords) {
  var killerPos = CoordinatesToMap(killerCoords);
  var victimPos = CoordinatesToMap(victimCoords);
  if (killerPos[1] >= victimPos[1]) {
    // killer below victim, message above victim
    return [(victimPos[0] + killerPos[0]) / 2 - 50, victimPos[1] - 40];
  }
  // killer above victim, message below victim
  return [(victimPos[0] + killerPos[0]) / 2 - 50, victimPos[1] + 15];
}


function isSuicide(d) {
  return d.killerClass == 'DeathTrigger' || d.killerSteamId == d.victimSteamId && d.killerSteamId != 0;
}


function addDeathIcon(selection, weapon) {
  var pos = getDeathIconPos(weapon);
  selection.style({
    'display': 'inline-block',
    'background-image': 'url(images/icons/inventory_icons.png)',
    'background-size': '100% auto',
    'background-position': function() {
      return '0px ' + -deathIconSize * pos + 'px';
    },
    'width': 2 * deathIconSize + 'px',
    'height': deathIconSize + 'px'
  });
}


function addMinimapBlip(selection, className, colorFilter, position, angle) {
  var coords = CoordinatesToMap(position);
  var pos = getBlipIconPos(className);
  selection.style({
    position: 'absolute',
    left: function() {
      return coords[0] - minimapBlipSize / 2 + 'px';
    },
    top: function() {
      return coords[1] - minimapBlipSize / 2 + 'px';
    },
    'background-image': 'url(images/icons/minimap_blip.png)',
    'background-size': minimapBlipBackgroundSize + 'px ',
    'background-position': function() {
      return -minimapBlipSize * pos[1] + 'px ' + -minimapBlipSize * pos[0] + 'px';
    },
    filter: colorFilter,
    '-webkit-filter': colorFilter,
    width: minimapBlipSize + 'px',
    height: minimapBlipSize + 'px'
  });

  if (angle) {
    selection.each(function() {
      rotateElement(d3.select(this), angle);
    });
  }
}


function getBlipIconPos(className) {
  switch (className) {
    case 'Rifle':
    case 'Shotgun':
    case 'Flamethrower':
    case 'GrenadeLauncher':
    case 'HeavyMachineGun':
      return [1, 0];
    case 'Exo':
      return [1, 1];
    case 'Skulk':
      return [2, 0];
    case 'Gorge':
      return [2, 1];
    case 'Lerk':
      return [2, 2];
    case 'Fade':
      return [2, 3];
    case 'Onos':
      return [2, 4];
    case 'SkulkEgg':
    case 'GorgeEgg':
    case 'FadeEgg':
    case 'LerkEgg':
    case 'OnosEgg':
    case 'Evolving':
    case 'Embryo':
      return [5, 6];
    case 'Sentry':
      return [3, 4];
    case 'Hydra':
      return [5, 5];
    case 'Whip':
      return [6, 2];
    case 'WhipBomb':
      return [6, 3];
    case 'BileBomb': // Contamination
      return [0, 5];
    case 'None':
      return [7, 3];
    default: // 'Hidden', 'Dead', 'Commander', 'Void', 'Spectator', 'Babbler'
      return [7, 4];
  }
}


function getDeathIconPos(weapon) {
  var deathMessageIcons = ['None',
    'Rifle', 'RifleButt', 'Pistol', 'Axe', 'Shotgun',
    'Flamethrower', 'ARC', 'GrenadeLauncher', 'Sentry', 'Welder',
    'Bite', 'Hydra', 'Spray', 'Spikes', 'Parasite',
    'Spores', 'Swipe', 'BuildAbility', 'Whip', 'BileBomb',
    'LayMines', 'Gore', 'Spit', 'Jetpack', 'Claw',
    'Minigun', 'Vortex', 'LerkBite', 'Umbra',
    'Xenocide', 'Blink', 'Leap', 'Stomp',
    'Consumed', 'GL', 'Recycled', 'Babbler', 'Railgun', 'BabblerAbility', 'GorgeTunnel', 'BoneShield',
    'ClusterGrenade', 'GasGrenade', 'PulseGrenade', 'Stab', 'WhipBomb', 'Metabolize', 'Crush', 'PowerSurge', 'HeavyMachineGun'
  ];
  var index = deathMessageIcons.indexOf(weapon);
  return index >= 0 ? index : 0; // i.e. 'DeathTrigger'
}


// active entities use their own location and icon
function isActiveEntity(weapon, killerClass) {
  switch (weapon) {
    case 'LayMines':
    case 'Sentry':
    case 'Babbler':
    case 'Spores':
    case 'Whip':
    case 'WhipBomb':
    case 'Hydra':
      return true;
    case 'BileBomb': // Contamination
      return killerClass == 'Commander' || killerClass == 'Dead';
    case 'GrenadeLauncher':
    case 'ClusterGrenade':
    case 'PulseGrenade':
      return killerClass == 'Dead'; // only show if killer is dead
    default:
      return false;
  }
}


function getColorFilterForBlipIcon(className, highlight) {
  switch (className) {
    case 'Rifle':
    case 'Shotgun':
    case 'Flamethrower':
    case 'GrenadeLauncher':
    case 'Exo':
    case 'Sentry':
      return highlight ? 'url(#marineLightBlue)' : 'url(#marineBlue)';
    case 'Skulk':
    case 'Gorge':
    case 'Fade':
    case 'Lerk':
    case 'Onos':
    case 'SkulkEgg':
    case 'GorgeEgg':
    case 'FadeEgg':
    case 'LerkEgg':
    case 'OnosEgg':
    case 'Evolving':
    case 'Embryo':
    case 'Whip':
    case 'WhipBomb':
    case 'Hydra':
    case '':
      return highlight ? 'url(#alienGold)' : 'url(#alienOrange)';
    default: // 'Hidden', 'Dead', 'Commander', 'Void', 'Spectator'
      return '';
  }
}


function getRelationBetweenPoints(point1, point2) {
  var dx = point1[0] - point2[0];
  var dy = point1[1] - point2[1];
  var dist = Math.sqrt(dx * dx + dy * dy);

  var angle = Math.PI - Math.atan2(-dy, dx);
  return [angle, dist];
}


function drawLine(selection, point1, point2, visible) {
  var temp = getRelationBetweenPoints(point1, point2);
  var angle = temp[0];
  var dist = temp[1];
  var x = (point1[0] + point2[0]) / 2 - dist / 2;
  var y = (point1[1] + point2[1]) / 2;

  selection
    .style({
      width: dist + 'px',
      position: 'absolute',
      display: visible === false ? 'none' : 'block',
      top: y + 'px',
      left: x + 'px'
    })
    .each(function() {
      rotateElement(d3.select(this), angle);
    });
}


function rotateElement(selection, angle) {
  selection.style({
    '-moz-transform': 'rotate(' + angle + 'rad)',
    '-webkit-transform': 'rotate(' + angle + 'rad)',
    '-o-transform': 'rotate(' + angle + 'rad)',
    '-ms-transform': 'rotate(' + angle + 'rad)'
  });
}

/* convert 3D game coords to x,y screen coord (0,0 is top left) */
function CoordinatesToMap(coordsString) {
  var coordsSplit = coordsString.split(' ');
  var coords = {
    x: Number(coordsSplit[0]),
    y: Number(coordsSplit[1]),
    z: Number(coordsSplit[2])
  };

  var map = d3.selectAll('#map img')
    .node();
  var mapWidth = map.offsetWidth;
  var mapHeight = map.offsetHeight;
  var mapLeft = map.offsetLeft;
  var mapTop = map.offsetTop;

  var x = (coords.z - mapExtents.origin.z) / mapExtents.scale.xzMax * mapWidth + mapWidth / 2 + mapLeft;
  var y = -(coords.x - mapExtents.origin.x) / mapExtents.scale.xzMax * mapHeight + mapHeight / 2 + mapTop;

  return [x, y];
}


function CoordinatesToHeatMap(coordsString) {
  var coordsSplit = coordsString.split(' ');
  var coords = {
    x: Number(coordsSplit[0]),
    y: Number(coordsSplit[1]),
    z: Number(coordsSplit[2])
  };

  var x = (coords.z - mapExtents.origin.z) / mapExtents.scale.xzMax * heatMapConfig.width + heatMapConfig.width / 2;
  var y = -(coords.x - mapExtents.origin.x) / mapExtents.scale.xzMax * heatMapConfig.height + heatMapConfig.height / 2;

  return {
    x: x,
    y: y,
    value: heatMapConfig.value
  };
}


function winningTeamToIcon(winner) {
  if (winner == 1) return '😎';
  if (winner == 2) return '👽';
  return '😶';
  // M 😎 😇 ☻ 😉
  // A 👽 😈 ☺ 👾😸😼
  // D 😴 😶 😐
}


function saveMapExtents(minimapExtentsString) {
  var minimapExtents = JSON.parse(minimapExtentsString);

  var originTemp = minimapExtents.origin.split(' ');
  mapExtents.origin.x = Number(originTemp[0]);
  mapExtents.origin.y = Number(originTemp[1]);
  mapExtents.origin.z = Number(originTemp[2]);

  var scaleTemp = minimapExtents.scale.split(' ');
  mapExtents.scale.x = Number(scaleTemp[0]);
  mapExtents.scale.y = Number(scaleTemp[1]);
  mapExtents.scale.z = Number(scaleTemp[2]);
  mapExtents.scale.xzMax = Math.max(mapExtents.scale.x / 2, mapExtents.scale.z / 2);
}


function resetHeatMap() {
  heatMap.setData({
    max: heatMapConfig.max,
    data: []
  });

  d3.select('#killsOverlay')
    .selectAll('div')
    .each(function(d) {
      d.wasVisible = false;
    });
}


function resizeHeatmap() {
  var map = d3.selectAll('#map img')
    .node();
  var mapWidth = map.offsetWidth;
  var mapHeight = map.offsetHeight;
  var mapLeft = map.offsetLeft;
  var mapTop = map.offsetTop;

  d3.selectAll('#heatMapContainer canvas')
    .style({
      left: mapLeft + 'px',
      top: mapTop + 'px',
      height: mapHeight + 'px',
      width: mapWidth + 'px'
    });
}

d3.selection.prototype.lastNode = function() {
  for (var t = this.length, n = t - 1; n >= 0; n--) {
    for (var e = this[n], u = e.length, r = u - 1; r >= 0; r--) {
      var i = e[r];
      if (i) return i;
    }
  }
  return null;
};


function updateRounds(responseText) {
  var queryData = JSON.parse(responseText);
  var rounds = d3.select('#roundSelect')
    .selectAll('option')
    .data(queryData);

  rounds.enter()
    .append('option');

  rounds.attr('value',
      function(d) {
        return d.roundId;
      })
    .text(function(d) {
      return d.roundId + ' | ' + d.roundDate + ' ' + winningTeamToIcon(d.winningTeam);
    })
    .property('selected', function(d, i) {
      return i == 0; // select first round and deselect all others
    });

  rounds.exit()
    .remove();

  saveMapExtents(queryData[queryData.length - 1].minimapExtents);

  // we recieved new rounds so request the killfeed for the selected one
  requestKillFeed();
}


function getSelectedRoundIds() {
  var roundSelect = d3.select('#roundSelect')
    .node();
  var result = [];

  if (roundSelect.selectedIndex < 0) {
    roundSelect.options[0].selected = true;
  }

  for (var i = 0; i < roundSelect.options.length; i++) {
    if (roundSelect.options[i].selected) {
      result.push(roundSelect.options[i].value);
    }
  }
  return result;
}


function getSelectedMap() {
  var mapSelect = d3.select('#mapSelect')
    .node();

  if (mapSelect.selectedIndex < 0) {
    mapSelect.options[mapSelect.options.length - 1].selected = true; // default select last map in list
    return mapSelect.options[mapSelect.options.length - 1].value;
  }

  return mapSelect.options[mapSelect.selectedIndex].value;
}


function requestKillFeed() {
  var roundIds = getSelectedRoundIds();
  var queryFilters = d3.select('#queryFilters')
    .node()
    .value.split('\n');
  if (queryFilters.length > 0 && queryFilters[0] != '' && !queryFilters[0].startsWith('&')) {
    queryFilters[0] = '&' + queryFilters[0];
  }
  var queryString = 'table=NamedKillFeed&order_by=gameTime&roundId_is=' + roundIds.join(',') + queryFilters.join('&');
  SendQuery('query.php?' + queryString, updateKillFeed);
}


function requestRoundList() {
  var mapName = getSelectedMap();
  var queryString = 'table=RoundInfo&data=roundId,roundDate,winningTeam,minimapExtents&mapName_is=' + mapName + '&order_by=roundDate_desc';
  loadMinimap(mapName);
  resetHeatMap();
  SendQuery('query.php?' + queryString, updateRounds);
}


function loadMinimap(mapName) {
  d3.select('#map img')
    .attr('src', 'images/minimaps/' + mapName + '.png')
    .attr('alt', mapName)
    .on('error', function() {
      d3.select('#map img')
        .attr('src', 'images/minimaps/nominimap.png')
        .attr('alt', 'images/minimaps/' + mapName + '.png not found');
      resizeHeatmap();
      drawKillFeed();
    })
    .on('load', function() {
      resizeHeatmap();
      drawKillFeed();
    });
}


function showQueryFiltersHelp() {
  alert('One filter per line, uses logical AND, is case sensitive\n\
  Examples:\n\
  victimSteamId_is=988933\n\
  killerSteamId_is=142453,634142\n\
  victimLocation_is=System Waypointing\n\
  killerLocation_is=Sub-Sector\n\
  victimName_is=Brute\n\
  killerName_mt=[Bb]r*te\n\
  victimClass_is=Exo\n\
  killerClass_is=Fade,Lerk,Onos\n\
  killerWeapon_is=Xenocide\n\
  killerTeamNumber_is=2\n\
  gameTime_le=300');
}


function SendQuery(url, callback) {
  var xhttp = new XMLHttpRequest();
  var argscopy = Array.prototype.slice.call(arguments, 1);
  xhttp.onreadystatechange = function() {
    if (xhttp.readyState == 4 && xhttp.status == 200) {
      argscopy[0] = xhttp.responseText;
      return callback.apply(this, argscopy);
    }
    return null;
  };
  console.log('Sending GET to ' + url);
  xhttp.open('GET', url, true);
  xhttp.send();
}


Date.now = function() {
  return new Date()
    .getTime();
};


HTMLElement.prototype.setClass = function(className, addClass) {
  if (addClass === null) addClass = true;
  var classes = this.className;
  var pattern = new RegExp('\\b' + className + '\\b');
  if (pattern.test(classes)) {
    if (!addClass) {
      this.className = classes.replace(pattern, '')
        .replace(/ +/, ' ')
        .replace(/ $/, '')
        .replace(/^ /, '');
    }
  } else if (addClass) {
    this.className += ' ' + className;
  }
};


Math.clamp = function(min, number, max) {
  return Math.max(min, Math.min(number, max));
};


function dump() {
  var argumentsArray = Array.prototype.slice.apply(arguments);
  if (argumentsArray.length == 0) return;
  var s = [];
  for (var i in argumentsArray) {
    s[i] = JSON.stringify(argumentsArray[i]);
  }
  console.log(s.join(' '));
}


window.onresize = function() {
  resizeHeatmap();
  drawKillFeed();
};


function main() {
  d3.select('#mapSelect')
    .on('change', requestRoundList);

  d3.select('#roundSelect')
    .on('change', requestKillFeed);

  d3.select('#queryFilters')
    .on('change', requestKillFeed);

  d3.select('#queryFiltersHelp')
    .on('click', showQueryFiltersHelp);

  d3.select('#gameTimeSlider')
    .on('change', onSliderAdvance)
    .on('input', onSliderAdvance); // dragging the slider

  d3.select('#controlsPlay')
    .on('click', playerStartPause);

  d3.select('#controlsJumpBack')
    .on('click', playerJumpBack);

  d3.select('#controlsJumpForward')
    .on('click', playerJumpForward);

  d3.selectAll('#controlsSpeed .radioGroup')
    .on('click', function() {
      playerSpeed = Number(d3.select(this)
        .node()
        .value);
    })
    .each(function() {
      var control = d3.select(this);
      if (control.node()
        .checked) {
        var controlSpeed = Number(control.node()
          .value);
        playerSpeed = controlSpeed;
      }
    });

  heatMapConfig.container = d3.select('#heatMapContainer')
    .style({
      width: heatMapConfig.width + 'px',
      height: heatMapConfig.height + 'px'
    })
    .node();

  heatMap = h337.create(heatMapConfig);

  resetHeatMap();

  d3.select('#heatMapToggle')
    .on('click', function() {
      var showHeatmap = d3.select(this)
        .node()
        .checked;

      d3.select('#heatMapOverlay')
        .style({
          display: showHeatmap ? 'block' : 'none'
        });
    });

  d3.select('#heatMapOverlay')
    .style({
      display: d3.select('#heatMapToggle')
        .node()
        .checked ? 'block' : 'none'
    });

  dump(); // dummy call to pacify linter

  setTimeout(requestRoundList, 0);
}


main();
// TODO BUG PulseGrenades have wrong killerTeamNumber (sometimes)
// TODO CHECK lerkbite poison is active entity?
// TODO HMG
