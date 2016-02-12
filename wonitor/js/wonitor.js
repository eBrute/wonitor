/* global d3, HUSL, Plotly */
var colors = [];
var teamColors = [];
var serverColors = [];
var huslTeamHues = [254.7, 18.4, 127.6]; // => #1369cb,#c03c0d,#0e7c0c
var huslServerHues = [344.6, 270.5, 225, 127.6, 288.5, 254.7, 18.4]; // => #cb0f7f,#6f45fa,#0f7490,#aa15e2,#0e7c0c,#1369cb,#c03c0d
var huslHues = [259.6, 245.1, 225, 197.5, 161.5, 145.6, 133.9, 127.7,
  120.2, 109.6, 78.9, 55.6, 33.4, 18.3, 10.1, 0, 344.6, 324.5, 298.1,
  288.5, 280.1, 273.7, 270.5, 266.3]; // => #1d61e6,#186fab,#16748f,#15767a,#147861,#137a4f,#137b35,#127c12,#3a7912,#527512,#746c12,#896412,#a25713,#bf3d13,#d3132b,#cf155b,#c9167e,#c1189f,#b21bca,#a81ddf,#9922f6,#7f3ef6,#6f47f6,#5551f6
var huslSat = 95;
var huslLight = 45;
var gridcolor = '#444444';

var fields = {
  'numRounds': { isNum: true, isNotNative: true, name: '# Rounds' },
  'count': { isNum: true, isNotNative: true, name: 'Count' },
  'id': { isNum: true, name: 'Round Id' },
  'serverName': { isNum: false, name: 'Server Name' },
  'serverIp': { isNum: false, name: 'Server Ip' },
  'serverPort': { isNum: false, name: 'Server Port' },
  'serverId': { isNum: false, name: 'Server' },
  'version': { isNum: true, name: 'Version' },
  'modIds': { isNum: false, name: 'ModIds' },
  'time': { isNum: false, name: 'Time' },
  'map': { isNum: false, name: 'Map' },
  'winner': { isNum: false, name: 'Winner', legend: { 0: 'Draw', 1: 'Marines', 2: 'Aliens' } },
  'length': { isNum: true, isFloat: true, name: 'Round Length', unit: 's' },
  'isTournamentMode': { isNum: false, name: 'Tournament Mode', legend: { 0: 'Disabled', 1: 'Enabled' } },
  'isRookieServer': { isNum: false, name: 'Rookie Server', legend: { 0: 'Disabled', 1: 'Enabled' } },
  'startPathDistance': { isNum: true, isFloat: true, name: 'Start Distance', unit: 'm' },
  'startHiveTech': { isNum: false, name: 'First Hive Tech', legend: { 'None': 'None', 'CragHive': 'Crag Hive', 'ShiftHive': 'Shift Hive', 'ShadeHive': 'Shade Hive' } },
  'startLocation1': { isNum: false, name: 'Marine Start Location' },
  'startLocation2': { isNum: false, name: 'Alien Start Location' },
  'numPlayers1': { isNum: true, name: '# Marine Players' },
  'numPlayers2': { isNum: true, name: '# Alien Players' },
  'numPlayersRR': { isNum: true, name: '# RR Players' },
  'numPlayersSpec': { isNum: true, name: '# Spectators' },
  'numPlayers': { isNum: true, name: '# Players' },
  'maxPlayers': { isNum: true, name: 'Max Players' },
  'numRookies1': { isNum: true, name: '# Marine Rookies' },
  'numRookies2': { isNum: true, name: '# Alien Rookies' },
  'numRookiesRR': { isNum: true, name: '# RR Rookies' },
  'numRookiesSpec': { isNum: true, name: '# Spectator Rookies' },
  'numRookies': { isNum: true, name: '# Rookies' },
  'skillTeam1': { isNum: true, name: 'Marines Skill' },
  'skillTeam2': { isNum: true, name: 'Aliens Skill' },
  'averageSkill': { isNum: true, isFloat: true, name: 'Average Skill' },
  'team1Wins': { isNum: true, isNotNative: true, name: '# Marine Wins' },
  'team2Wins': { isNum: true, isNotNative: true, name: '# Alien Wins' },
  'draws': { isNum: true, isNotNative: true, name: '# Draws' },
  'killsTeam1': { isNum: true, name: '# Marine Kills' },
  'killsTeam2': { isNum: true, name: '# Alien Kills' },
  'kills': { isNum: true, name: '# Kills' },
  'numRTs1': { isNum: true, name: '# Extractors' },
  'numRTs2': { isNum: true, name: '# Harvesters' },
  'numRTs': { isNum: true, name: '# Captured Resource Points' },
  'numHives': { isNum: true, name: '# Remaining Hives' },
  'numCCs': { isNum: true, name: '# Remaining Command Stations' },
  'numTechPointsCaptured': { isNum: true, name: '# Captured Techpoints' },
  'biomassLevel': { isNum: true, name: 'Biomass Level' }
};

var serverData = {};
var defaultTimeFormat = 'Y-m-d H:i:s'; // as returned by sql in php notation

function OnServerDataRecieve(responseText) {
  serverData = JSON.parse(responseText);
  var i;

  // add servernames for serverids
  if (serverData.servers) {
    fields.serverId.legend = {};
    for (i = 0; i < serverData.servers.length; i++) {
      var serverId = serverData.servers[i].serverId;
      var serverName = serverData.servers[i].serverName;
      fields.serverId.legend[serverId] = serverName;
    }
  }

  createColors();

  var plotDivs = xpath('//div[@plotSpecs or @plotspecs]');
  var configurator = xpath0('id("configurator")');
  if (configurator) {
    ReloadExamplesOnCLick();
    BuildConfigurator();

    if (plotDivs.length == 1 && plotDivs[0].getAttribute('plotSpecs') == '' && xpath0('id("configurator")')) {
      plotDivs[0].setAttribute('plotSpecs', window.location.hash);
    }
  }

  // collect heights before adding the plots to prevent headaches concerning fluid layout
  var widths = [];
  var heights = [];
  for (i = 0; i < plotDivs.length; i++) {
    widths.push(plotDivs[i].offsetWidth - 1);
    heights.push(plotDivs[i].offsetHeight);
  }

  for (i = 0; i < plotDivs.length; i++) {
    plotDivs[i].style.width = widths[i] + 'px';
    plotDivs[i].style.height = heights[i] + 'px';
    MakePlot(plotDivs[i], widths[i], heights[i]);
  }
}


function BuildConfigurator() {
  var xAxisCheckbox = xpath0('id("xAxisCheckbox")');
  var yAxisCheckbox = xpath0('id("yAxisCheckbox")');
  var sAxisCheckbox = xpath0('id("sAxisCheckbox")');
  var tAxisCheckbox = xpath0('id("tAxisCheckbox")');

  var xAxisSelector = xpath0('id("xAxisSelector")');
  var yAxisSelector = xpath0('id("yAxisSelector")');
  var sAxisSelector = xpath0('id("sAxisSelector")');
  var tAxisSelector = xpath0('id("tAxisSelector")');

  // add selectors
  for (var key in fields) {
    d3.select('#xAxisSelector').append('option').text(fields[key].name).attr('value', key);
    d3.select('#yAxisSelector').append('option').text(fields[key].name).attr('value', key);
    if (fields[key].isNum) {
      d3.select('#sAxisSelector').append('option').text(fields[key].name).attr('value', key);
    }
    d3.select('#tAxisSelector').append('option').text(fields[key].name).attr('value', key);
    if (fields[key].isNotNative) continue;
    d3.select('#constraintsAdd').append('option').text(fields[key].name).attr('value', key);
  }

  // add event listeners
  function addCheckboxEventListener(checkbox) {
    checkbox.addEventListener('change', function() {
      var axisRow = xpath0('id("' + this.id + '")/ancestor::tr');
      axisRow.setClass('axisDisabled', !this.checked);
    });
  }

  addCheckboxEventListener(xAxisCheckbox);
  addCheckboxEventListener(yAxisCheckbox);
  addCheckboxEventListener(sAxisCheckbox);
  addCheckboxEventListener(tAxisCheckbox);

  function addSelectorEventListener(selector, numOptions) {
    selector.addEventListener('change', function() {
      var field = this.value;
      var axisOptionsRows = xpath('id("' + this.id + '")/ancestor::tr/following-sibling::tr[position()<=' + numOptions + ']');
      var hideAccumulationOptions = !fields[field].isNum || (fields[field].isNotNative || false);
      var hideArithmeticOptions = !fields[field].isNum;
      var hideTimeFormatOptions = field != 'time';
      for (var i = 0; i < axisOptionsRows.length; i++) {
        if (i == 0) axisOptionsRows[i].setClass('axisOptionHidden', hideAccumulationOptions);
        if (i == 1) axisOptionsRows[i].setClass('axisOptionHidden', hideArithmeticOptions);
        if (i == 2) axisOptionsRows[i].setClass('axisOptionHidden', hideTimeFormatOptions);
      }
    });
  }

  addSelectorEventListener(xAxisSelector, 3);
  addSelectorEventListener(yAxisSelector, 3);
  addSelectorEventListener(sAxisSelector, 3);
  addSelectorEventListener(tAxisSelector, 3);

  var constraintsAdd = xpath0('id("constraintsAdd")');
  constraintsAdd.addEventListener('click', function() {
    if (this.selectedIndex <= 0) return;
    var field = this.value;
    this.options[0].selected = true;
    AddConstraintToTable(field + '_is');
  });

  constraintsAdd.options[0].selected = true;

  var applyButton = xpath0('id("applyButton")');
  applyButton.addEventListener('click', function() {
    var newPlotSpecs = PlotConfigToString();
    var plotDiv = xpath0('id("plotDiv")');
    window.location.href = '#' + newPlotSpecs;
    plotDiv.setAttribute('plotSpecs', '#' + newPlotSpecs);
    MakePlot(plotDiv);
  });

  var timeFormatHelpText = '\
j  Day of Month (1-31)\n\
d  Day of Month (01-31)\n\
S  Day Suffix (st,nd,rd,th)\n\
w  Day of Week (0-6)\n\
D  Day of Week (Mon,Tue,..)\n\
l  Day of Week (Monday,Tuesday,..)\n\
z  Day of Year (1-365)\n\
W  Week of Year (1-52)\n\
F  Month (January,February,..)\n\
m  Month (01-12)\n\
M  Month (Jan,Feb,..)\n\
n  Month (1-12)\n\
t  Days in Month (28,29,30,31)\n\
L  Leap Year (1,0)\n\
Y  Year (2015,2016,..)\n\
y  Year (15,16,..)\n\
a  Part of Day (am,pm)\n\
A  Part of Day (AM,PM)\n\
g  Hour (1-12)\n\
h  Hour (01-12)\n\
G  Hour (0-23)\n\
H  Hour (00-23)\n\
i  Minute (00-59)\n\
s  Second (00-59)\
';

  var timeFormatInputs = xpath('//*[contains(@class,"timeFormatInput")]');
  var i, helper;
  for (i = 0; i < timeFormatInputs.length; i++) {
    helper = document.createElement('span');
    helper.textContent = '❓';
    helper.className = 'hoverinfo';
    helper.title = timeFormatHelpText;
    helper.addEventListener('click', function() {
      alert(timeFormatHelpText);
    });
    var timeFormatInput = timeFormatInputs[i];
    if (timeFormatInput.nextSibling) {
      timeFormatInput.parentNode.insertBefore(helper, timeFormatInput.nextSibling);
    } else {
      timeFormatInput.parentNode.appendChild(helper);
    }
  }

  var textInfoHelpText = 'label+value+percent+text,all,none';
  var textInfoInputs = xpath('//*[contains(@class,"textInfoInput")]');
  for (i = 0; i < textInfoInputs.length; i++) {
    helper = document.createElement('span');
    helper.textContent = '❓';
    helper.className = 'hoverinfo';
    helper.title = textInfoHelpText;
    helper.addEventListener('click', function() {
      alert(textInfoHelpText);
    });
    var textInfoInput = textInfoInputs[i];
    if (textInfoInput.nextSibling) {
      textInfoInput.parentNode.insertBefore(helper, textInfoInput.nextSibling);
    } else {
      textInfoInput.parentNode.appendChild(helper);
    }
  }
}


function MakePlot(plotDiv, optionalWidth, optionalHeight) {
  var plotSpecs = GetPlotSpecsFromString(plotDiv.getAttribute('plotSpecs'));

  // set some default values;
  plotSpecs['x'] = plotSpecs['x'] || 'winner';
  plotSpecs['y'] = plotSpecs['y'] || 'count';
  if (!plotDiv.id) {
    var i = 0;
    while (xpath0('id("plotDiv' + i + '")')) {
      i++;
    }
    plotDiv.id = 'plotDiv' + i;
  }
  plotSpecs['plotDiv'] = plotDiv.id;
  if (plotDiv.offsetWidth > 0)  plotSpecs.width  = plotDiv.offsetWidth;
  if (plotDiv.offsetHeight > 0) plotSpecs.height = plotDiv.offsetHeight;
  if (optionalWidth) plotSpecs.width = optionalWidth;
  if (optionalHeight) plotSpecs.height = optionalHeight;

  var configurator = xpath0('id("configurator")');
  if (configurator) {
    SetPlotConfigData(plotSpecs);
  }

  var queryString = BuildQueryString(plotSpecs);
  SendQuery('query.php?' + queryString, CreatePlot, plotSpecs);
}


function SetPlotConfigData(plotSpecs) {

  function setAxisConfigData(axis) {
    var axisCheckbox = xpath0('id("' + axis + 'AxisCheckbox")');
    axisCheckbox.checked = plotSpecs[axis] !== null;
    axisCheckbox.dispatchEvent(new Event('change'));

    var axisField = GetFieldName(plotSpecs[axis]);
    var axisFieldSelect = xpath0('id("' + axis + 'AxisSelector")');
    var axisFieldOption = xpath0('./option[@value="' + axisField + '"]', axisFieldSelect);
    if (axisFieldOption) axisFieldOption.selected = true;
    axisFieldSelect.dispatchEvent(new Event('change'));

    function getAccOperation(fieldName) {
      if (!fieldName) return '';
      if (fieldName.endsWith('_sum')) return 'sum';
      if (fieldName.endsWith('_avg')) return 'avg';
      if (fieldName.endsWith('_cnt')) return '';
      if (plotSpecs[axis + 'BinSize'] !== null) return 'every';
      return '';
    }

    var accOp = getAccOperation(plotSpecs[axis]);
    var accInput = xpath0('id("' + axis + 'AxisCheckbox")/ancestor::tr/following-sibling::tr[position()=1]//input[@type="radio"][@value="' + accOp + '"]');
    if (accInput) accInput.checked = true;

    var binSizeInput = xpath0('id("' + axis + 'AxisCheckbox")/ancestor::tr/following-sibling::tr[position()=1]//input[@type="text"]');
    if (binSizeInput) binSizeInput.value = plotSpecs[axis + 'BinSize'] ? plotSpecs[axis + 'BinSize'] : '';

    var scaleInputs = xpath('id("' + axis + 'AxisCheckbox")/ancestor::tr/following-sibling::tr[position()=2]//input[@type="text"]');
    scaleInputs[0].value = plotSpecs[axis + 'Scale'] ? plotSpecs[axis + 'Scale'] : '';
    scaleInputs[1].value = plotSpecs[axis + 'ScaleBy'] ? plotSpecs[axis + 'ScaleBy'] : '';

    var timeFormatInput = xpath0('id("' + axis + 'AxisCheckbox")/ancestor::tr/following-sibling::tr[position()=3]//input[@type="text"]');
    timeFormatInput.value = plotSpecs[axis + 'TimeFormat'] ? unescape(plotSpecs[axis + 'TimeFormat']) : defaultTimeFormat;
  }

  setAxisConfigData('x');
  setAxisConfigData('y');
  setAxisConfigData('s');
  setAxisConfigData('t');

  // advanced options
  var advancedOptions = xpath0('id("advOptions")/tbody');
  var lineNumber = 1;
  var i;

  var plotTypeSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (plotSpecs['plotType']) {
    for (i = 0; i < plotTypeSelect.options.length; i++) {
      if (plotTypeSelect.options[i].value == plotSpecs['plotType']) {
        plotTypeSelect.options[i].selected = true;
        break;
      }
    }
  } else {
    plotTypeSelect.options[0].selected = true;
  }

  var ySortSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (plotSpecs['ySort']) {
    for (i = 0; i < ySortSelect.options.length; i++) {
      if (ySortSelect.options[i].value == plotSpecs['ySort']) {
        ySortSelect.options[i].selected = true;
        break;
      }
    }
  } else {
    ySortSelect.options[2].selected = true;
  }

  var tSortSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (plotSpecs['tSort']) {
    for (i = 0; i < tSortSelect.options.length; i++) {
      if (tSortSelect.options[i].value == plotSpecs['tSort']) {
        tSortSelect.options[i].selected = true;
        break;
      }
    }
  } else {
    tSortSelect.options[0].selected = true;
  }

  var autoRotateInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  autoRotateInput.checked = plotSpecs['autoRotate'] ? plotSpecs['autoRotate'] : false;

  var hideLegendInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  hideLegendInput.checked = plotSpecs['hideLegend'] ? plotSpecs['hideLegend'] : false;

  var tNormalizeInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  tNormalizeInput.checked = plotSpecs['tNormalize'] ? plotSpecs['tNormalize'] : false;

  var scatterScaleInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  scatterScaleInput.value = plotSpecs['sizeRef'] ? plotSpecs['sizeRef'] : '';

  var textInfoInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  textInfoInput.value = plotSpecs['textInfo'] ? unescape(plotSpecs['textInfo']) : '';

  var xLabelInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  xLabelInput.value = plotSpecs['xLabel'] ? unescape(plotSpecs['xLabel']) : '';

  var yLabelInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  yLabelInput.value = plotSpecs['yLabel'] ? unescape(plotSpecs['yLabel']) : '';

  var titleInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  titleInput.value = plotSpecs['title'] ? unescape(plotSpecs['title']) : '';

  // add constraints
  ClearConstraintTable();
  for (var key in plotSpecs) {
    if (!IsKeyConstraint(key)) continue;
    AddConstraintToTable(key, plotSpecs[key]);
  }
}


function ClearConstraintTable() {
  var oldConstraints = xpath('id("constraintsTable")/tbody/tr');
  for (var i = 0; i < oldConstraints.length; i++) {
    oldConstraints[i].remove();
  }
}


function AddConstraintToTable(constraint, constraintValue) {
  var split = constraint.split('_');
  if (split.length < 2) return;
  var constraintField = split[split.length - 2];
  var constraintOperator = split[split.length - 1] || 'is';

  // unhide constraints if we manually add the first one
  if (constraintValue === null && xpath('id("constraintsTable")/tbody/tr').length == 0) {
    xpath0('id("constToggle")').checked = false;
  }

  // add a new row
  var row = d3.select('#constraintsTable')
    .select('tbody')
    .append('tr').attr('value', constraint);
  row.append('td').attr('value', constraintField).text(fields[constraintField].name + (Boolean(fields[constraintField].unit) ? ' (in ' + fields[constraintField].unit + ')' : ''));

  function indexOfStartLocation(startLocation) {
    if (!serverData.startLocations) return [null, -1];
    for (var map in serverData.startLocations) {
      var startLocations = serverData.startLocations[map];
      for (var i = 0; i < startLocations.length; i++) {
        if (startLocations[i] == startLocation) {
          return [map, i];
        }
      }
    }
    return [null, -1];
  }

  var cell, select, i, map;
  if ((constraint == 'map_is' || constraint == 'map_ne') && (constraintValue === null || serverData.maps && serverData.maps.indexOf(constraintValue) != -1)) {
    cell = row.append('td');
    select = cell.append('select');
    select.append('option').attr('value', 'is').text('=').property('selected', constraintOperator == 'is');
    select.append('option').attr('value', 'ne').text('≠').property('selected', constraintOperator == 'ne');
    cell.append('span');

    cell = row.append('td');
    select = cell.append('select');
    for (i = 0; i < serverData.maps.length; i++) {
      map = serverData.maps[i];
      select.append('option').attr('value', map).text(map).property('selected', constraintValue == map);
    }
    cell.append('span');
  } else if ((constraint == 'startLocation1_is' || constraint == 'startLocation1_ne' || constraint == 'startLocation2_is' || constraint == 'startLocation2_ne') && (constraintValue === null || indexOfStartLocation(constraintValue)[1] != -1)) {
    cell = row.append('td');
    select = cell.append('select');
    select.append('option').attr('value', 'is').text('=').property('selected', constraintOperator == 'is');
    select.append('option').attr('value', 'ne').text('≠').property('selected', constraintOperator == 'ne');
    cell.append('span');

    cell = row.append('td');
    select = cell.append('select');
    for (map in serverData.startLocations) {
      var optgroup = select.append('optgroup').attr('label', map);
      for (i = 0; i < serverData.startLocations[map].length; i++) {
        var startLocation = serverData.startLocations[map][i];
        optgroup.append('option').attr('value', startLocation).text(startLocation).property('selected', constraintValue == startLocation);
      }
    }
    cell.append('span');
  } else if (fields[constraintField] && fields[constraintField].legend && (constraintOperator == 'is' || constraintOperator == 'ne') && (constraintValue === null || fields[constraintField].legend[constraintValue] !== null)) {
    cell = row.append('td');
    select = cell.append('select');
    select.append('option').attr('value', 'is').text('=').property('selected', constraintOperator == 'is');
    select.append('option').attr('value', 'ne').text('≠').property('selected', constraintOperator == 'ne');

    cell = row.append('td');
    select = cell.append('select');
    for (var value in fields[constraintField].legend) {
      var name = fields[constraintField].legend[value];
      select.append('option').attr('value', value).text(name).property('selected', constraintValue == value);
    }
    cell.append('span');
  } else {
    cell = row.append('td');
    select = cell.append('select');
    select.append('option').attr('value', 'gt').text('>').property('selected', constraintOperator == 'gt');
    select.append('option').attr('value', 'ge').text('≥').property('selected', constraintOperator == 'ge');
    select.append('option').attr('value', 'is').text('=').property('selected', constraintOperator == 'is');
    select.append('option').attr('value', 'ne').text('≠').property('selected', constraintOperator == 'ne');
    select.append('option').attr('value', 'le').text('≤').property('selected', constraintOperator == 'le');
    select.append('option').attr('value', 'lt').text('<').property('selected', constraintOperator == 'lt');
    cell.append('span');
    row.append('td').append('input').attr('type', 'text').attr('size', '8').attr('value', constraintValue ? constraintValue : '');
  }

  var remover = row.append('td').append('button').classed('removeButton', true).text('⨯'); // TODO replace with 🗙 as soon as win/chrome supports it
  remover.node().addEventListener('click', function() {
    var rowToRemove = xpath0('./ancestor::tr', this);
    rowToRemove.remove();
  });
}


function GetPlotSpecsFromString(str) {
  var args = {};
  if (str.startsWith('#')) str = str.substr(1);
  var pairs = str.split('&');
  for (var i = 0; i < pairs.length; i++) {
    var key = pairs[i].split('=')[0];
    var value = pairs[i].split('=')[1] || true;
    args[key] = value;
  }
  return args;
}


function GetFieldIsAggregated(fieldName) {
  if (!fieldName) return false;
  if (
    fieldName == 'count' ||
    fieldName == 'numRounds' ||
    fieldName == 'team1Wins' ||
    fieldName == 'team2Wins' ||
    fieldName == 'draws' ||
    fieldName == 'teamWins' ||
    fieldName.endsWith('_sum') ||
    fieldName.endsWith('_avg') ||
    fieldName.endsWith('_cnt')
  ) return true;
  return false;
}


function IsKeyConstraint(key) {
  return key.endsWith('_is') ||
  key.endsWith('_ne') ||
  key.endsWith('_gt') ||
  key.endsWith('_ge') ||
  key.endsWith('_lt') ||
  key.endsWith('_le');
}


function BuildQueryString(plotSpecs) {
  var query = [];
  var yIsAggregated = GetFieldIsAggregated(plotSpecs['y']);
  var xIsAggregated = GetFieldIsAggregated(plotSpecs['x']);
  var sIsAggregated = GetFieldIsAggregated(plotSpecs['s']);
  var yIsGrouped = plotSpecs['yBinSize'] !== null;
  var xIsGrouped = plotSpecs['xBinSize'] !== null;
  var tIsGrouped = plotSpecs['tBinSize'] !== null;
  var data = [];
  var groups = [];
  plotSpecs.groupIndices = {};
  for (var key in plotSpecs) {
    if (IsKeyConstraint(key)) {
      query.push(key + '=' + plotSpecs[key]);
    }

    if (/^[xyst]$/.test(key)) {
      data.push(plotSpecs[key]);
    }

    if (key == 'y') {
      if (!yIsAggregated && sIsAggregated || yIsGrouped) {
        groups.push(plotSpecs[key] + (yIsGrouped && plotSpecs['yBinSize'] ? '_every_' + plotSpecs['yBinSize'] : ''));
        plotSpecs.groupIndices[key] = groups.length;
      }
    }

    if (key == 'x') {
      if (!xIsAggregated && (yIsAggregated || yIsGrouped || sIsAggregated) || xIsGrouped) {
        groups.push(plotSpecs[key] + (xIsGrouped && plotSpecs['xBinSize'] ? '_every_' + plotSpecs['xBinSize'] : ''));
        plotSpecs.groupIndices[key] = groups.length;
      }
    }

    if (key == 't') {
      if (yIsAggregated || sIsAggregated || tIsGrouped) {
        groups.push(plotSpecs[key] + (tIsGrouped && plotSpecs['tBinSize'] ? '_every_' + plotSpecs['tBinSize'] : ''));
        plotSpecs.groupIndices[key] = groups.length;
      }
    }
  }

  query.push('data=' + data.join(','));
  if (groups.length) {
    query.push('group_by=' + groups.join(','));
  }

  return query.join('&');
}


function SendQuery(url, callback) {
  var xhttp = new XMLHttpRequest();
  var argscopy = Array.prototype.slice.call(arguments, 1);
  xhttp.onreadystatechange = function() {
    if (xhttp.readyState == 4 && xhttp.status == 200) {
      argscopy[0] = xhttp.responseText;
      return callback.apply(this, argscopy);
    }
  };
  console.log('Sending GET to ' + url); // NOTE poor man's API Documentation 😉
  xhttp.open('GET', url, true);
  xhttp.send();
}


function GetFieldName(field) {
  if (!field) return null;
  var fieldName = field;
  if (
    fieldName.endsWith('_sum') ||
    fieldName.endsWith('_avg') ||
    fieldName.endsWith('_cnt')
  ) {
    fieldName = fieldName.slice(0, -4);
  }
  if (fieldName.split('_every_').length > 1) {
    fieldName = fieldName.split('_every_')[0];
  }
  return fieldName;
}


function GetFieldIsNum(field) {
  if (!field) return false;
  var fieldName = GetFieldName(field);
  if (!fields[fieldName].isNum) return false;
  return true;
}


function GetFullFieldName(field) {
  if (!field) return '';
  var fieldName = GetFieldName(field);
  if (!fields[fieldName]) return field;

  if (fields[fieldName].name) {
    fieldName = fields[fieldName].name;
  }

  var fieldMethod = '';
  if (field.endsWith('_sum')) { fieldMethod = ' Sum'; }
  if (field.endsWith('_avg')) { fieldMethod = ' Average'; }
  if (field.endsWith('_cnt')) { fieldMethod = ' Count'; }

  return fieldName + fieldMethod;
}


function CreatePlot(responseText, plotSpecs) {
  var queryData = JSON.parse(responseText);

  var plotDiv = xpath0('id("' + plotSpecs['plotDiv'] + '")');
  var fontSize = parseInt(window.getComputedStyle(plotDiv, null).getPropertyValue('font-size'), 10);
  var fontColor = window.getComputedStyle(plotDiv, null).getPropertyValue('color');

  var plotData = [];
  var plotLayout = GetPlotLayoutDefaults(fontSize, fontColor);
  var plotOptions = GetPlotOptionsDefault();

  var tType = plotSpecs['t'];
  var xType = plotSpecs['x'];
  var yType = plotSpecs['y'];
  var sType = plotSpecs['s'];

  if (!yType) return;

  var tAxisName = GetFullFieldName(tType);
  var xAxisName = GetFullFieldName(xType);
  var yAxisName = GetFullFieldName(yType);
  var sAxisName = GetFullFieldName(sType);

  var tSrcField = tType;
  var xSrcField = xType;
  var ySrcField = yType;
  var sSrcField = sType;

  var tIsNum = GetFieldIsNum(tType);
  var xIsNum = GetFieldIsNum(xType);
  var yIsNum = GetFieldIsNum(yType);
  var sIsNum = GetFieldIsNum(sType);

  var tIsGrouped = plotSpecs['tBinSize'] !== null;
  var sIsAggregated = GetFieldIsAggregated(sType);
  var yIsAggregated = GetFieldIsAggregated(yType);
  var xIsGrouped = plotSpecs['xBinSize'] !== null;

  if (plotSpecs.groupIndices['t']) tSrcField = 'group' + plotSpecs.groupIndices['t'];
  if (plotSpecs.groupIndices['x']) xSrcField = 'group' + plotSpecs.groupIndices['x'];
  if (plotSpecs.groupIndices['y']) ySrcField = 'group' + plotSpecs.groupIndices['y'];
  if (plotSpecs.groupIndices['s']) sSrcField = 'group' + plotSpecs.groupIndices['s'];

  var xDataField = 'x';
  var yDataField = 'y';
  var plotType = 'bar';
  if (!yIsNum || !yIsAggregated && xType != 'id' || sType && sIsNum) plotType = 'scatter';
  if (yType == 'count' && !tType || plotSpecs['pie'] && !tType) plotType = 'pie';
  if (plotSpecs['plotType']) plotType = plotSpecs['plotType'];
  if (plotType != 'pie' && plotType != 'scatter' && plotType != 'lines' && plotType != 'bar') return;
  if (plotType == 'pie') {
    xDataField = 'labels';
    yDataField = 'values';
  }

  // collect traces
  var traces = [];
  var i, t;
  if (tType) {
    for (i = 0; i < queryData.length; i++) {
      var traceValue = queryData[i][tSrcField];
      if (tType == 'time' && plotSpecs['tTimeFormat']) traceValue = (new Date(traceValue.replace(' ', 'T'))).format(unescape(plotSpecs['tTimeFormat']));
      if (traces.indexOf(traceValue) == -1) {
        traces.push(traceValue);
      }
    }
  } else {
    traces.push('');
  }

  var useTeamColors   = tType == 'winner'   || !tType && plotType == 'pie' && xType == 'winner';
  var useServerColors = tType == 'serverId' || !tType && plotType == 'pie' && xType == 'serverId';

  // sort traces
  if (tType == 'winner') {
    traces.sort(function(a, b) { // 1,2,0
      if (a == 0) return 1;
      if (b == 0) return -1;
      return a - b;
    });
  } else if (!plotSpecs['tSort'] || plotSpecs['tSort'] == 'asc') {
    traces.sort(function(a, b) {
      return alphanumCaseInsensitiveCompare(a, b);
    });
  } else if (plotSpecs['tSort'] == 'desc') {
    traces.sort(function(a, b) {
      return alphanumCaseInsensitiveCompare(b, a);
    });
  }

  // add empty traces
  for (i = 0; i < traces.length; i++) {
    var margins;
    if (plotType == 'pie') {
      plotData[i] = {
        labels: [],
        values: [],
        text: [],
        marker: {
          colors: useTeamColors ? teamColors : useServerColors ? serverColors : colors.concat(colors) // double the amount of colors so we dont fall back to default color palette
        },
        textposition: 'inside',
        textinfo: plotSpecs['textInfo'] ? plotSpecs['textInfo'] : 'all',
        hoverinfo: 'label+value', // 'label+value+percent+text'
        sort: false, // NOTE we sort manually to not mess up color order
        pull: 0.01,
        type: plotType
      };

      margins = [0, 0, 0, 0];
      if (plotSpecs['margin']) {
        if (plotSpecs['margin'].split(',').length == 4) {
          margins = plotSpecs['margin'].split(',');
        } else {
          margins[0] = margins[1] = margins[2] = margins[3] = parseInt(plotSpecs['margin'], 10);
        }
      }

      plotLayout.margin = {
        t: margins[0],
        r: margins[1],
        b: margins[2],
        l: margins[3],
        pad: plotSpecs['pad'] ? parseInt(plotSpecs['pad'], 10) : 5,
        autoexpand: true
      };
    } else {

      // BAR SCATTER
      var traceName = traces[i];
      if (tIsNum && plotSpecs['tScale'])   traceName *= plotSpecs['tScale'];
      if (tIsNum && plotSpecs['tScaleBy']) traceName /= plotSpecs['tScaleBy'];
      if (fields[tType] && fields[tType].legend && typeof fields[tType].legend[traceName] !== 'undefined') traceName = fields[tType].legend[traceName];

      plotData[i] = {
        name: traceName,
        x: [],
        y: [],
        text: [],
        hoverinfo: 'x+y+text',
        type: plotType == 'lines' ? 'scatter' : plotType,
        marker: {
          color: useTeamColors ? teamColors[i % teamColors.length] : useServerColors ? serverColors[i % serverColors.length] : colors[i % colors.length]
        }
      };

      if (plotType == 'scatter' || plotType == 'lines') {
        plotData[i].mode = plotType == 'lines' ? 'lines' : 'markers';
        if (sType) {
          plotData[i].marker.size = [];
          plotData[i].marker.sizemode = 'area';
        }
        if (plotSpecs['sizeRef']) {
          plotData[i].marker.sizeref = plotSpecs['sizeRef'];
        }
        plotData[i].hoverinfo = 'all'; // 'x+y+z';
        if (plotType == 'lines') {
          plotData[i].line = {
            shape: 'linear', // linear, spline, hvh
            width: 4
          };
          plotData[i].fill = 'tozeroy';
        }
      }

      margins = [
        plotSpecs['title'] ? fontSize * 4 : fontSize * 2, // top
        0, // right
        xIsNum ? fontSize * 4 : fontSize * 7, // bottom
        yIsNum ? fontSize * 4 : fontSize * 9 // left
      ];
      if (plotSpecs['margin']) {
        if (plotSpecs['margin'].split(',').length == 4) {
          margins = plotSpecs['margin'].split(',');
        } else {
          margins[0] = margins[1] = margins[2] = margins[3] = parseInt(plotSpecs['margin'], 10);
        }
      }

      plotLayout.margin = {
        t: margins[0],
        r: margins[1],
        b: margins[2],
        l: margins[3],
        pad: plotSpecs['pad'] ? parseInt(plotSpecs['pad'], 10) : 5,
        autoexpand: true
      };

      plotLayout.yaxis = {
        title: plotSpecs['yLabel'] ? unescape(plotSpecs['yLabel']) : GetFullFieldName(yType),
        ticks: 'inside',
        showticklabels: true,
        tickmode: 'auto',
        tickfont: { size: Math.max(6, fontSize - 2) },
        showgrid: true,
        gridcolor: gridcolor
      };

      plotLayout.xaxis = {
        title: plotSpecs['xLabel'] ? unescape(plotSpecs['xLabel']) : GetFullFieldName(xType),
        ticks: 'inside',
        showticklabels: true,
        tickmode: 'auto',
        tickfont: { size: Math.max(6, fontSize - 2) },
        showgrid: true,
        gridcolor: gridcolor
      };

      // always rotate text labels by 45° so margin-handling is more predictable (if we dont do this, they are either 0°, 45° or 90°)
      if (!xIsNum) {
        plotLayout.xaxis.tickangle = 45;
      }

      if (plotType == 'bar') {
        plotLayout.xaxis.showgrid = false;
        plotLayout.yaxis.showgrid = false;
      }
    }
  }

  // sort data
  if (plotType == 'pie' && xType != 'winner') {
    queryData.sort(function(a, b) {
      return alphanumCaseInsensitiveCompare(b[ySrcField], a[ySrcField]);
    });
  } else if (plotType == 'pie' && xType == 'winner') {
    queryData.sort(function(a, b) { // 1,2,0
      var aa = parseInt(a[xSrcField], 10);
      var bb = parseInt(b[xSrcField], 10);
      if (aa == 0) return 1;
      if (bb == 0) return -1;
      return aa - bb;
    });
  } else { // plotType != 'pie'
    if (plotSpecs['ySort'] == 'asc') {
      queryData.sort(function(a, b) {
        return alphanumCaseInsensitiveCompare(a[ySrcField], b[ySrcField]);
      });
    } else if (plotSpecs['ySort'] == 'desc') {
      queryData.sort(function(a, b) {
        return alphanumCaseInsensitiveCompare(b[ySrcField], a[ySrcField]);
      });
    } else {
      queryData.sort(function(a, b) {
        return alphanumCaseInsensitiveCompare(a[xSrcField], b[xSrcField]);
      });
    }
  }

  // add data to traces
  for (i = 0; i < queryData.length; i++) {
    var tValue = queryData[i][tSrcField];
    var xValue = queryData[i][xSrcField];
    var yValue = queryData[i][ySrcField];
    var sValue = queryData[i][sSrcField];

    if (tType) {
      if (tType == 'time' && plotSpecs['tTimeFormat']) tValue = (new Date(tValue.replace(' ', 'T'))).format(unescape(plotSpecs['tTimeFormat']));
    }

    // find trace
    var trace = 0;
    if (tValue !== null) {
      trace = traces.indexOf(tValue);
    }

    // scale and translate values
    if (tType) {
      if (tIsNum && plotSpecs['tScale']) tValue *= plotSpecs['tScale'];
      if (tIsNum && plotSpecs['tScaleBy']) tValue /= plotSpecs['tScaleBy'];
      if (fields[tType] && fields[tType].legend && typeof fields[tType].legend[tValue] !== 'undefined') tValue = fields[tType].legend[tValue];
    }
    if (xType) {
      if (xIsNum && plotSpecs['xScale']) xValue *= plotSpecs['xScale'];
      if (xIsNum && plotSpecs['xScaleBy']) xValue /= plotSpecs['xScaleBy'];
      if (fields[xType] && fields[xType].legend && typeof fields[xType].legend[xValue] !== 'undefined') xValue = fields[xType].legend[xValue];
      if (plotSpecs['xTimeFormat'] && xType == 'time') xValue = (new Date(xValue.replace(' ', 'T'))).format(unescape(plotSpecs['xTimeFormat']));
    }
    if (yType) {
      if (yIsNum && plotSpecs['yScale']) yValue *= plotSpecs['yScale'];
      if (yIsNum && plotSpecs['yScaleBy']) yValue /= plotSpecs['yScaleBy'];
      if (fields[yType] && fields[yType].legend && typeof fields[yType].legend[yValue] !== 'undefined') yValue = fields[yType].legend[yValue];
      if (plotSpecs['yTimeFormat'] && yType == 'time') yValue = (new Date(yValue.replace(' ', 'T'))).format(unescape(plotSpecs['yTimeFormat']));
    }
    if (sType) {
      if (sIsNum && plotSpecs['sScale']) sValue *= plotSpecs['sScale'];
      if (sIsNum && plotSpecs['sScaleBy']) sValue /= plotSpecs['sScaleBy'];
    }

    // add data to trace
    plotData[trace][yDataField].push(yValue);
    plotData[trace][xDataField].push(xValue);
    if (sType && sIsNum && plotData[trace].marker.size) {
      plotData[trace].marker.size.push(sValue);
    }
    if (tValue !== null && sValue === null) {
      plotData[trace]['text'].push(tAxisName + (tIsGrouped ? ' >' : ' ') + tValue);
    }
    if (tValue !== null && sValue !== null) {
      plotData[trace]['text'].push(sAxisName + ' ' + sValue);
    }
    if (tValue === null && sValue === null) {
      plotData[trace]['text'].push(xValue + (xIsGrouped ? '+' : ''));
    }
  } // for every trace

  // normalize across traces
  if (plotSpecs['tNormalize'] && yIsNum && tType) {
    var sums = {};
    for (t = 0; t < plotData.length; t++) {
      for (i = 0; i < plotData[t][xDataField].length; i++) {
        var x = plotData[t][xDataField][i];
        var y = parseFloat(plotData[t][yDataField][i]);
        if (!sums[x]) sums[x] = y;
        else sums[x] += y;
      }
    }
    for (t = 0; t < plotData.length; t++) {
      plotData[t].text = [];
      for (i = 0; i < plotData[t][xDataField].length; i++) {
        plotData[t].text.push(plotData[t][yDataField][i] + ' of ' + sums[plotData[t][xDataField][i]]);
        plotData[t][yDataField][i] = parseFloat(plotData[t][yDataField][i]) / sums[plotData[t][xDataField][i]];
      }
    }
  }

  // swap axes
  if (plotSpecs['autoRotate'] && plotType == 'bar' && plotData[0] && plotData[0][xDataField].length > 3) {
    for (t = 0; t < plotData.length; t++) {
      var tmp = plotData[t][xDataField];
      plotData[t][xDataField] = plotData[t][yDataField];
      plotData[t][yDataField] = tmp;
      plotData[t].orientation = 'h';
    }
    var tmp = plotLayout.xaxis;
    plotLayout.xaxis = plotLayout.yaxis;
    plotLayout.yaxis = tmp;
  }

  // stack bars
  if (plotType == 'bar' && plotData[0] && plotData[0][xDataField].length > 1) {
    plotLayout.barmode = 'stack';
  }

  // show legend for traces
  if (tType) {
    plotLayout.showlegend = plotSpecs['hideLegend'] ? !plotSpecs['hideLegend'] : true;
  }

  if (plotSpecs['width']) {
    plotLayout.width = plotSpecs['width'];
  }

  if (plotSpecs['height']) {
    plotLayout.height = plotSpecs['height'];
  }

  if (plotSpecs['title']) {
    plotLayout.title = unescape(plotSpecs['title']);
  }
  // else {
    // TODO Auto Title
  // }

  // delete old plot
  RemovePlot(plotDiv);

  // create new plot
  Plotly.newPlot(plotSpecs['plotDiv'], plotData, plotLayout, plotOptions);

  AddGradientDefinitionsToChart(plotDiv);
  SetColorGradientsForChart(plotDiv, useTeamColors, useServerColors);
}


function PlotConfigToString() {
  var arr = [];
  var i;

  // get axes
  function getAxisConfigData(axis) {
    var axisCheckbox = xpath0('id("' + axis + 'AxisCheckbox")');
    if (!axisCheckbox.checked) return '';
    var axisFieldSelect = xpath0('id("' + axis + 'AxisSelector")');
    var axisField = axisFieldSelect.value;

    var useAccumulationOptions = fields[axisField].isNum && !fields[axisField].isNotNative;
    var useArithmeticOptions = fields[axisField].isNum;
    var useTimeFormatOptions = axisField == 'time';

    if (useAccumulationOptions) {
      var accOptions = xpath('./ancestor::tr/following-sibling::tr[position()=1]//input[@type="radio"]', axisCheckbox);
      for (i = 0; i < accOptions.length; i++) {
        if (accOptions[i].checked) {
          switch (accOptions[i].value) {
            default:
            case '':    arr.push(axis + '=' + axisField); break;
            case 'sum': arr.push(axis + '=' + axisField + '_sum'); break;
            case 'avg': arr.push(axis + '=' + axisField + '_avg'); break;
            case 'every':
              arr.push(axis + '=' + axisField);
              var binSize = xpath0('./ancestor::td//input[@type="text"]', accOptions[i]).value;
              if (binSize > 0) {
                arr.push(axis + 'BinSize=' + binSize);
              }
              break;
          }
          break;
        }
      }
    } else {
      arr.push(axis + '=' + axisField);
    }

    if (useArithmeticOptions) {
      var aritOptions = xpath('./ancestor::tr/following-sibling::tr[position()=2]//input[@type="text"]', axisCheckbox);
      for (i = 0; i < aritOptions.length; i++) {
        if (aritOptions[i].value == '' || aritOptions[i].value == 0) continue;
        if (i == 0) arr.push(axis + 'Scale=' + aritOptions[i].value);
        if (i == 1) arr.push(axis + 'ScaleBy=' + aritOptions[i].value);
      }
    }

    if (useTimeFormatOptions) {
      var timeFormatInput = xpath0('./ancestor::tr/following-sibling::tr[position()=3]//input[@type="text"]', axisCheckbox);
      if (timeFormatInput.value !== '' && timeFormatInput.value != defaultTimeFormat) {
        arr.push(axis + 'TimeFormat=' + timeFormatInput.value); // NOTE no escape() for now
      }
    }
  }

  getAxisConfigData('x');
  getAxisConfigData('y');
  getAxisConfigData('s');
  getAxisConfigData('t');

  // get advanced config
  var advancedOptions = xpath0('id("advOptions")/tbody');
  var lineNumber = 1;

  var plotTypeSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (plotTypeSelect.value != '') {
    arr.push('plotType=' + plotTypeSelect.value);
  }

  var ySortSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (ySortSelect.value != 'none') {
    arr.push('ySort=' + ySortSelect.value);
  }

  var tSortSelect = xpath0('./tr[position()=' + lineNumber++ + ']//select', advancedOptions);
  if (tSortSelect.value != 'asc') {
    arr.push('tSort=' + tSortSelect.value);
  }

  var autoRotateInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  if (autoRotateInput.checked) {
    arr.push('autoRotate');
  }

  var hideLegendInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  if (hideLegendInput.checked) {
    arr.push('hideLegend');
  }

  var tNormalizeInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="checkbox"]', advancedOptions);
  if (tNormalizeInput.checked) {
    arr.push('tNormalize');
  }

  var scatterScaleInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  if (scatterScaleInput.value != '' && scatterScaleInput.value != 0) {
    arr.push('sizeRef=' + scatterScaleInput.value);
  }

  var textInfoInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  if (textInfoInput.value != '' && textInfoInput.value != 'all') { // TODO only save if newPlotType == 'pie'
    arr.push('textInfo=' + escape(textInfoInput.value));
  }

  var xLabelInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  if (xLabelInput.value != '') {
    arr.push('xLabel=' + escape(xLabelInput.value));
  }

  var yLabelInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  if (yLabelInput.value != '') {
    arr.push('yLabel=' + escape(yLabelInput.value));
  }

  var titleInput = xpath0('./tr[position()=' + lineNumber++ + ']//input[@type="text"]', advancedOptions);
  if (titleInput.value != '') {
    arr.push('title=' + escape(titleInput.value));
  }

  // get constraints
  var constraintsRows = xpath('id("constraintsTable")/tbody/tr');
  for (i = 0; i < constraintsRows.length; i++) {
    var constraintField = xpath0('./td[1]', constraintsRows[i]).getAttribute('value');
    var constraintType = 'is';
    var constraintTypeSelect = xpath0('./td[2]/select', constraintsRows[i]);
    if (constraintTypeSelect) {
      constraintType = constraintTypeSelect.value;
    }
    var constraintValue = xpath0('./td[3]/select|./td[3]/input', constraintsRows[i]).value;
    if (constraintValue != '') {
      arr.push(constraintField + '_' + constraintType + '=' + constraintValue);
    }
  }

  return arr.join('&');
}


function GetPlotLayoutDefaults(fontSize, fontColor) {
  if (!fontSize) fontSize = 16;
  if (!fontColor) fontColor = 'white';
  return {
    font: {
      size: Math.max(8, fontSize),
      color: fontColor
    },
    titlefont: {
      size: Math.max(14, fontSize + 6),
      color: fontColor
    },
    legend: {
      font: {
        size: Math.max(8, fontSize),
        color: fontColor
      },
      traceorder: 'normal'
    },
    separators: '. ',
    autosize: true,
    showlegend: false,
    paper_bgcolor: 'transparent',
    plot_bgcolor: 'transparent',
    hidesources: true
  };
}


function GetPlotOptionsDefault() {
  return {
    displayModeBar: false,
    showLink: false
  };
}


function RemovePlot(div) {
  while (div.firstChild) {
    div.removeChild(div.firstChild);
  }
}


function AddVerticalLinearGradientDefinitionToChart(div, id, color1, color2, opacity1, opacity2) {
  opacity1 = typeof opacity1 !== 'undefined' ? opacity1 : 1;
  opacity2 = typeof opacity2 !== 'undefined' ? opacity2 : 1;

  var gradient = d3.select(div).selectAll('svg').select('defs')
    .append('svg:linearGradient')
    .attr('x1', '0')
    .attr('y1', '0')
    .attr('x2', '1')
    .attr('y2', '1')
    .attr('id', id);
  gradient.append('svg:stop')
    .attr('offset', '0%')
    .attr('stop-color', color1)
    .attr('stop-opacity', 1);
  gradient.append('svg:stop')
    .attr('offset', '100%')
    .attr('stop-color', color2)
    .attr('stop-opacity', 1);
}

function createColors() {
  var i;
  // HUSL obtained from http://www.husl-colors.org/
  for (i = 0; i < huslHues.length; i++) {
    colors[i % huslHues.length] = HUSL.toHex(huslHues[i], huslSat, huslLight);
  }

  for (i = 0; i < huslTeamHues.length; i++) {
    teamColors[i] = HUSL.toHex(huslTeamHues[i], huslSat, huslLight);
  }

  for (i = 0; i < huslServerHues.length; i++) {
    serverColors[i] = HUSL.toHex(huslServerHues[i], huslSat, huslLight);
  }
}


function AddGradientDefinitionsToChart(div) {
  function clamp(val) {
    return Math.max(0, Math.min(100, val));
  }

  function darker(color) {
    var colvals = HUSL.fromHex(color);
    return HUSL.toHex(colvals[0], colvals[1], clamp(colvals[2] - 25));
  }

  function lighter(color) {
    var colvals = HUSL.fromHex(color);
    return HUSL.toHex(colvals[0], colvals[1], clamp(colvals[2] + 10));
  }

  var i;
  for (i = 0; i < colors.length; i++) {
    AddVerticalLinearGradientDefinitionToChart(div, 'gradient' + (i + 1), lighter(colors[i]), darker(colors[i]));
  }

  for (i = 0; i < teamColors.length; i++) {
    AddVerticalLinearGradientDefinitionToChart(div, 'gradientTeam' + (i + 1), lighter(teamColors[i]), darker(teamColors[i]));
  }

  for (i = 0; i < serverColors.length; i++) {
    AddVerticalLinearGradientDefinitionToChart(div, 'gradientServer' + (i + 1), lighter(serverColors[i]), darker(serverColors[i]));
  }

  // AddVerticalLinearGradientDefinitionToChart(div, 'gradientGold',  'hsl( 29,88%,60%)', 'hsl( 29,88%,25%)');
}


function SetColorGradientsForChart(div, useTeamColors, useServerColors) {
  var num = colors.length;
  var i;
  for (i = 1; i <= colors.length; i++) {
    d3.select(div).selectAll('.slice:nth-of-type(' + num + 'n+' + i + ')').select('path').style('fill', 'url(#gradient' + i + ')'); // slices
    d3.select(div).selectAll('.trace:nth-of-type(' + num + 'n+' + i + ')').select('.points').selectAll('path').style('fill', 'url(#gradient' + i + ')'); // traces
    d3.select(div).selectAll('.traces:nth-of-type(' + num + 'n+' + i + ')').selectAll('.legendsymbols').select('g').select('path').style('fill', 'url(#gradient' + i + ')'); // legend
  }

  if (useTeamColors) {
    for (i = 1; i <= serverColors.length; i++) {
      d3.select(div).selectAll('.slice:nth-of-type(' + i + ')').select('path').style('fill', 'url(#gradientTeam' + i + ')'); // slices
      d3.select(div).selectAll('.trace:nth-of-type(' + i + ')').select('.points').selectAll('path').style('fill', 'url(#gradientTeam' + i + ')'); // traces
      d3.select(div).selectAll('.traces:nth-of-type(' + i + ')').selectAll('.legendsymbols').select('g').select('path').style('fill', 'url(#gradientTeam' + i + ')'); // legend
    }
  }

  if (useServerColors) {
    for (i = 1; i <= serverColors.length; i++) {
      d3.select(div).selectAll('.slice:nth-of-type(' + i + ')').select('path').style('fill', 'url(#gradientServer' + i + ')'); // slices
      d3.select(div).selectAll('.trace:nth-of-type(' + i + ')').select('.points').selectAll('path').style('fill', 'url(#gradientServer' + i + ')'); // traces
      d3.select(div).selectAll('.traces:nth-of-type(' + i + ')').selectAll('.legendsymbols').select('g').select('path').style('fill', 'url(#gradientServer' + i + ')'); // legend
    }
  }
}


function ReloadExamplesOnCLick() {
  var links = xpath('//aside[contains(@class,"aside-examples")]//li/a');
  for (var i = 0; i < links.length; i++) {
    links[i].addEventListener('click', function() {
      var plotDiv = xpath0('id("plotDiv")');
      window.location.href = this.href;
      plotDiv.setAttribute('plotSpecs', window.location.hash);
      MakePlot(plotDiv);
    });
  }
}


HTMLElement.prototype.setClass = function(className, addClass) {
  if (addClass === null) addClass = true;
  var classes = this.className;
  var pattern = new RegExp('\\b' + className + '\\b');
  if (pattern.test(classes)) {
    if (!addClass) {
      this.className = classes.replace(pattern, '').replace(/ +/, ' ').replace(/ $/, '').replace(/^ /, '');
    }
  } else {
    if (addClass) {
      this.className += ' ' + className;
    }
  }
};


Date.prototype.getMonthName = function() {
  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  return monthNames[this.getMonth()];
};
Date.prototype.getMonthAbbr = function() {
  var monthAbbrs = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return monthAbbrs[this.getMonth()];
};
Date.prototype.getDayFull = function() {
  var daysFull = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  return daysFull[this.getDay()];
};
Date.prototype.getDayAbbr = function() {
  var daysAbbr = ['Sun', 'Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat'];
  return daysAbbr[this.getDay()];
};
Date.prototype.getDayOfYear = function() {
  var onejan = new Date(this.getFullYear(), 0, 1);
  return Math.ceil((this - onejan) / 86400000);
};
Date.prototype.getDaySuffix = function() {
  var d = this.getDate();
  var sfx = ['th', 'st', 'nd', 'rd'];
  var val = d % 100;
  return (sfx[(val - 20) % 10] || sfx[val] || sfx[0]);
};
Date.prototype.getWeekOfYear = function() {
  var onejan = new Date(this.getFullYear(), 0, 1);
  return Math.ceil(((this - onejan) / 86400000 + onejan.getDay() + 1) / 7);
};
Date.prototype.isLeapYear = function() {
  var yr = this.getFullYear();
  if (parseInt(yr, 10) % 400 == 0) return true;
  if (parseInt(yr, 10) % 100 == 0) return false;
  if (parseInt(yr, 10) % 4 == 0) return true;
  return false;
};
Date.prototype.getMonthDayCount = function() {
  var monthDayCounts = [31, this.isLeapYear() ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  return monthDayCounts[this.getMonth()];
};
Date.prototype.format = function(dateFormat) {
  var date = this.getDate();
  var month = this.getMonth();
  var hours = this.getHours();
  var minutes = this.getMinutes();
  var seconds = this.getSeconds();

  var dateString = '';
  dateFormat = dateFormat.split('');
  for (var i = 0; i < dateFormat.length; i++) {
    var f = dateFormat[i];
    var s;
    switch (f) {
      case 'd': s = date < 10 ? '0' + date : date; break;
      case 'D': s = this.getDayAbbr(); break;
      case 'j': s = this.getDate(); break;
      case 'l': s = this.getDayFull(); break;
      case 'S': s = this.getDaySuffix(); break;
      case 'w': s = this.getDay(); break;
      case 'z': s = this.getDayOfYear(); break;
      case 'W': s = this.getWeekOfYear(); break;
      case 'F': s = this.getMonthName(); break;
      case 'm': s = ('0' + (month + 1)).substr(-2); break;
      case 'M': s = this.getMonthAbbr(); break;
      case 'n': s = month + 1; break;
      case 't': s = this.getMonthDayCount(); break;
      case 'L': s = this.isLeapYear() ? '1' : '0'; break;
      case 'Y': s = this.getFullYear(); break;
      case 'y': s = String(this.getFullYear()).substr(-2); break;
      case 'a': s = hours > 12 ? 'pm' : 'am'; break;
      case 'A': s = hours > 12 ? 'PM' : 'AM'; break;
      case 'G': s = hours; break;
      case 'H': s = ('0' + hours).substr(-2); break;
      case 'g': s = hours % 12 > 0 ? hours % 12 : 12; break;
      case 'h': s = ('0' + (hours % 12 > 0 ? hours % 12 : 12)).substr(-2); break;
      case 'i': s = ('0' + minutes).substr(-2); break;
      case 's': s = ('0' + seconds).substr(-2); break;
      default: s = f;
    }
    dateString += s;
  }
  return dateString;
};


// taken from http://www.davekoelle.com/files/alphanum.js
function alphanumCaseInsensitiveCompare(a, b) {
  function chunkify(t) {
    var tz = [];
    var x = 0;
    var y = -1;
    var n = null;
    var i, j;

    while (i = (j = t.charAt(x++)).charCodeAt(0)) {
      var m = i >= 48 && i <= 57 || i == 46;
      if (m !== n) {
        tz[++y] = '';
        n = m;
      }
      tz[y] += j;
    }
    return tz;
  }

  var aa = chunkify(a.toLowerCase());
  var bb = chunkify(b.toLowerCase());

  for (var x = 0; aa[x] && bb[x]; x++) {
    if (aa[x] !== bb[x]) {
      var c = Number(aa[x]), d = Number(bb[x]);
      if (c == aa[x] && d == bb[x]) {
        return c - d;
      }
      return aa[x] > bb[x] ? 1 : -1;
    }
  }
  return aa.length - bb.length;
}


function xpath(p, context) {
  if (!context) context = document;
  var i, item, arr = [], xpr = document.evaluate(p, context, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
  for (i = 0; item = xpr.snapshotItem(i); i++) arr.push(item);
  return arr;
}


function xpath0(p, context) {
  if (!context) context = document;
  var xpr = document.evaluate(p, context, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
  return xpr.snapshotItem(0);
}

function Init() {
  SendQuery('getInfo.php', OnServerDataRecieve);
}

window.addEventListener('load', Init);
