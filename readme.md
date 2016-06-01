Wonitor - Server-side Statistics for Natural Selection 2
========================================================
Wonitor is a mod for the game Natural Selection 2. It runs on the server and collects certain data which is used for statistical analysis. The data is send to the Wonitor web server, where it is stored and can also be viewed. The mod can also be configured to save NS2+ statistics which are stored in a seperate database.

## If you are updating from an older version
Use the following steps when you update from the previous version
1. The whitelist has been moved to **config.php**, so locate **update.php** and note down the **$serverIdWhiteList* = ...** line

2. If you want to be extra secure, make a copy of you **/data** directory. The repository does not contain this directory (in which all data is stored) so the next step should be safe even if you skip this one.

3. Unpack the new version into the Wonitor directory, replace files when asked.

4. Rename **config.php.new** to **config.php** and edit it to insert your old whitelist (from step 1.) into the appropriate position.

5. Remove **index.html** (there is a new landing page now and some web servers are configured in a way that **index.php** has lower priority).

6. Visit **<yourURL>/wonitor/troubleshooting.php** to check if everything works as expected.

## Setting up the web server
Note: If you use an existing Wonitor web server, you only need to get your *ServerIdentifier* white-listed.
1. Setup a web server that runs PHP and SQLite3. <br />
   It should be reachable via HTTP, so make sure you have no redirect to HTTPS (for the bare minimum, *update.php* needs to be served via HTTP, you are free to use HTTPS for everything else)

2. Add the Wonitor files to your web server. <br />
  Make sure that the parent directory is writable by your webserver (Apache, Nginx, ...) <br />
  If you choose to move the .js and .css files, you will need to adjust the relative path in all .html and .php files.

3. Figure out how to reach your webserver. For now we will just assume you have everything hosted under *http://example.com/wonitor/*. You could as well use *localhost* if the web server and the ns2 game server are on the same machine.

4. Rename **config.php.new** to **config.php** and add the serverId of your game server to the whitelist (see [Whitelisting](#Whitelisting))

Note that the pages will not show anything, since there is no database yet. The database will be created after the first stats have been uploaded (after the first finished round).

## Setting up the game server
Note: You could do this part before setting up the web server. In this case Wonitor will probably throw some errors and deactivate itself until it gets reloaded (i.e. on mapchange).

1. Add the Wonitor mod to your NS2 server. <br />
  Mod ID: 235ee3a6 <br />
  http://steamcommunity.com/sharedfiles/filedetails/?id=593421222

  In order for the mod to work, Shine Administration mod needs to be running on the server: <br />
  Mod ID: 706d242 <br />
  http://steamcommunity.com/sharedfiles/filedetails/?id=117887554
  
  NS2+ is optional and only needed if you plan to (additionally) store stats provided by it:<br />
  Mod ID: 28eb0f83 <br />
  http://steamcommunity.com/sharedfiles/filedetails/?id=686493571

2. Create a mod config file. <br />
  You could either create it manually or run the mod without a config file, in which case an empty config will be generated for you. The config is located at **%configdir%/shine/plugins/wonitor.json** where **%configdir%** will usually be located in **%APPDATA%/Natural Selection 2** if not specified otherwise.

3. Edit the mod config file.  <br />
  An example could look like this:
  ```json
  {
    "ServerIdentifier":"MyServer",
    "WonitorURL":"http://example.com/wonitor/update.php",
    "SendWonitorStats":true,
    "SendNS2PlusStats":false,
    "SendNS2PlusStatsKillFeed":false,
    "ShowMenuEntry":true,
    "MenuEntryName":"Wonitor",
    "MenuEntryUrl":"http://example.com/wonitor/"
  }
  ```
  Detailed explanation of the different entries:
    * *ServerIdentifier* is some string that can be chosen freely. It is used to identify and authenticate your game server (see [Whitelisting](#Whitelisting)). Note that you should use a different ServerIdentifier per game server. Multiple servers using the same identifier will be treated as one, even if they have different names, ports and IP addresses. On the other hand, data containing a new ServerIdentifier will be treated as coming from a completely new server, even if server name and IP have not changed. This allows you to switch between different configurations on a server (i.e. custom maps, rookie friendly, modded) or move your game server to a new machine without interfering with the existing database.
    * *WonitorURL* should be pointing to **update.php** on your web server. If the webserver is running on the same machine, you can use ```"http://localhost/wonitor/update.php"```.
    * *SendWonitorStats* determines if Wonitor should collect and stats on round end. These are used on the landing page (index.php).
    * *SendNS2PlusStats* determines if Wonitor should try to send and save NS2+ stats on round end. These are used for deathMap.php.
    * *SendNS2PlusStatsKillFeed* determines if the NS2+ stats will include the KillFeed of each round. Only relevant when *SendNS2PlusStats* is set to true. The KillFeed can become quite big (~50MB for 1000 rounds), so in case you are not planning to use it, you can turn it off here.
    * *ShowMenuEntry* determines if a Wonitor button should be added to the Shine main menu. Clicking the button will open the landing page in the in-game Steam browser.
    * *MenuEntryName* is the string displayed in the Shine main menu (only relevant when *ShowMenuEntry* is true).
    * *MenuEntryUrl* is the URL to be opened (only relevant when *ShowMenuEntry* is true).

## <a name="Whitelisting"></a>Whitelisting
The Wonitor web server will discard any data it receives, that does not contain a valid white-listed *ServerIdentifier*. This prevents random people from sending bogus data to the server, and other server admins from using your web server without your consent.

The identifier is set in the mod config on the game server (see above). It is then send along with the data to the web server, where it is checked against the whitelist.
The whitelist is located at the top part of **config.php**. It may look like this
```js
$serverIdWhiteList = array('MyServer','MyOtherServer','someRandomString');
```
The list is comma separated and case sensitive. The serverIds are only stored and addressed in their hashed form, so if you keep your serverId secret and don't choose something easily guessable, you should be fine. Keep in mind that it is send in plain text over the Internet though, so don't use your favorite password here.

## Creating Charts
There are some predefined charts on the example page. Creating others is easy though. Use **configurator.html** to create the chart you like and copy its parameters from the URL. Add a DIV element with the parameters like this:
```html
<div plotSpecs="#x=winner&y=count&numPlayers_gt=10"></div>
```

When the javascript is loaded afterwards, the appropriate chart will be added to the DIV. The chart will inherit certain properties such as width, height, font-size, etc.

## NS2+ Integration
Wonitor is capable of saving statistics provided by the NS2+ mod. While these include all the information that Wonitor collects for itself (like round length and winning team), NS2+ tracks many things that Wonitor does not. This includes a full history of all buildings that are dropped/destroyed per round, player stats such as hits/misses per weapon or playtime for each class, a research history, a full kill feed (including information on the killer and the victim) and much more.
These detailed information come with at the price of increased storage size (probably at ~ 50 MB/1000 rounds, where Wonitor only uses ~ 300 kB / 1000 rounds).
When configured to store NS2+ statistics, they will be saved in a different database (**data/ns2plus.sqlite3**) that is completely independent from Wonitor's own database.
The main part of Wonitor does not rely on the NS2+ stats, so saving it remains optional. The mod does not rely on the NS2+ mod either and will check for its presence before attempting to gather data from it.
The downside of this approach of maintaining two independent databases is that only the round information that Wonitor collects for itself is accessible in the charts. Charts that make use of NS2+ data are not supported (yet).

## Query API
**query.php** allows to query the database. The type of query is determined by the GET parameters, the result is returned in JSON format. The following parameters are supported (all optional):
* **table** specifies which table to query. Defaults to **rounds** in the wonitor database. Other valid options are **RoundInfo**,**ExtendedRoundInfo**,**ServerInfo**,**Research**,**Buildings**,**MarineCommStats**,**PlayerRoundStats**,**PlayerStats**,**PlayerWeaponStats**,**PlayerClassStats**,**KillFeed**,**NamedKillFeed**, all of which are tables in the NS2+ database. Example: *'query.php?table=KillFeed'*

* **data** contains a comma separated list of field names (see below for a list of fields). I.e. *'query.php?data=id,map,numPlayers'* returns all ids and their respective maps and player counts.

  Some basic operations can be performed on regular fields (that are numbers) by appending the fieldname with one of the following operators:
  * **_avg** - averages the respective field
  * **_sum** - sums up the respective field
  * **_cnt** - counts the occurrences of the respective field

  Works best in conjunction with *group_by* (see below). I.e. *'query.php?data=length_avg'* gives the average round length and *'query.php?data=length_sum&group_by=map'* gives the total playtime for each map.

  Additional to regular fields, there are special fields that can be used in a query (these work only for the Wonitor database if not noted otherwise):
  * **all** - returns all fields (works for all tables)
  * **count** - counts the number of entries (works for all tables)
  * **numRounds** - number of rounds
  * **team1Wins** - number of rounds where marines won
  * **team2Wins** - number of rounds where aliens won
  * **draws**  - number of rounds where no one won
  * **teamWins** - shorthand for team1Wins, team2Wins, draws
  * **relTeam1Wins** - number of rounds where marines won divided by total number of rounds
  * **relTeam2Wins** - number of rounds where aliens won divided by total number of rounds
  * **relDraws** - number of draws divided by total number of rounds
  * **relTeamWins** - shorthand for relTeam1Wins, relTeam2Wins, relDraws
  * **winDiff** - win difference between both team (marines - aliens)
  * **relWinDiff** - relative win difference between both team ((marines - aliens)/(total number of rounds))
  * **skillDiff** - skill difference between both team (marines - aliens)
  * **startLocations** - shorthand for startLocation1, startLocation2
  * **serverInfo** - shorthand for serverName, serverIp, serverPort, serverId

* **group_by** contains a comma separated list of field names. Entries are grouped by those fields (in order). I.e. *'query.php?data=count&group_by=version,map'* lists the number of rounds on each map (as group2) for each version (as group1).

  For number fields, the grouped field can be appended by **_every_<num>** to round down the field towards the nearest multiple of <num> before grouping. I.e. *'query.php?data=count&group_by=numPlayers_every_10'* gives the number of rounds for player counts in between 0-9, 10-19, 20-29, etc., or *'query.php?data=count&group_by=length_every_60'* returns the number of rounds that lasted for 1 minute (60+ sec), 2 minutes (120+ sec), etc.

* **order_by** contains a comma separated list of field names. Entries are sorted by those fields (in order). I.e. *'query.php?data=numPlayers,map&order_by=numPlayers,map'* orders the result by number of players first. Entries with the same number of players are then sorted by map name.

  Sort direction can be specified for each field individually by appending either **_asc** or **_desc**. If none is given, ascending will be used. Example: *'query.php?data=numPlayers,map&order_by=numPlayers_asc,map_desc'*

* **constraints** can be placed on any regular field by using the field name and a constraint operator [**is** (=), **ne** (!=), **gt** (>), **ge** (>=), **lt** (<), **le** (<=)], i.e.*'query.php?data=id&map_is=ns2_summit'*.

  All constraints can handle a comma-separated array of values, i.e. *'query.php?data=id&map_is=ns2_summit,ns2_veil'*. For the **is** constraint, entries are chained with a logical OR. I.e. the previous example selects every round where the map is either *ns2_summit* OR *ns2_veil*. All other constraints use the logical AND. I.e. *'query.php?data=id&map_ne=ns2_summit,ns2_veil'* selects all rounds where the map is not *ns2_summit* AND is not *ns2_veil*.

  Multiple constraints are chained together with a logical AND, too, i.e. *'query.php?data=id&map_is=ns2_summit&length_gt=300&numPlayers_ge=10'* selects only those entries for which the map is summit, the round length exceeds 5 minutes and the player count is greater or equal ten.
  
  There are multiple operators for matching more complicated strings:
  * [**mt** (matching), **nm** (not matching)] uses [globbing](https://en.wikipedia.org/wiki/Glob_%28programming%29), i.e.
        query.php?data=id&map_mt=ns2_*
  * [**lk** (like), **nl** (not like)] uses [SQL Wildcards](http://www.w3schools.com/sql/sql_wildcards.asp), i.e.
        query.php?data=id&map_lk=ns2_%
  * [**re** (regexp), **nr** (not regexp)] uses [Regular Expressions](https://en.wikipedia.org/wiki/Regular_expression), i.e.
        query.php?data=id&map_mt=ns2_.*
    Support for regexp has to be enabled in your sqlite configuration and will most likely not work. Default to globbing.
    
  For map constraints, there exists the special keyword *@official* which is a shorthand for all official maps. The following queries are identical: *'query.php?data=id&map_is=@official'*, *'query.php?data=id&map_is=ns2_derelict,ns2_docking,ns2_kodiak,ns2_refinery,ns2_tram,ns2_biodome,ns2_descent,ns2_eclipse,ns2_mineshaft,ns2_summit,ns2_veil'*

* **showQuery** without parameters; reveals the underlying SQL query. I.e. *'query.php?data=count,map&group_by=map&length_ge=300&showQuery'* shows *'SELECT COUNT(1) AS count, map, map AS [group1] FROM rounds WHERE length >= :length_ge GROUP BY [group1]'*

**getInfo.php** contains a hard-wired query, that returns information on all servers, maps and start locations in the data base. Server data returned always refers to the most recent entry.

**deathMap.php** shows a minimap with all recorded death locations and additional information such as victim name and weapon used for one or multiple rounds. In order to work, Wonitor needs to be configured to save NS2+ data with the KillFeed information. If a minimap is missing or needs to be updated, locate *%mapname%.tga* in your NS2 directory (for official maps) or your NS2 Workshop directory(for custom maps, **%APPDATA%/Natural Selection 2/Workshop**), convert it into a PNG file and place it under **/images/minimaps**.

## Structure of the SQLite Files
If you wish, you could skip the query api and access the data directly.

**data/rounds.sqlite3** contains one entry for each finished round. Most fields are self-explanatory. In general, 1 is Marines, 2 is Aliens. For boolean values 0 is false/no/disabled and 1 is true/yes/enabled. All numbers refer to the respective round only.

Field  | Description
------------- | -------------
id | unique consecutive entry number
serverName | name of the server
serverIp | server IP address
serverPort | server port
serverId | hashed version of the ServerIdentifier
version | NS2 version
modIds | a json-stringified array of the mods installed
time | time of round end i.e. 2015-24-12 23:04:32
map | map name
winner | 0-Draw 1-Marines 2-Aliens
length | round length in seconds
isTournamentMode | tournament mode enabled/disabled
isRookieServer | rookie friendly server yes/no
startPathDistance | pathing distance between start locations in meters
startHiveTech | 'CragHive', 'ShiftHive', 'ShadeHive', 'None'
startLocation1 | name of the marine start location
startLocation2 | name of the marine start location
numPlayers1 | number of players in the marine team on round end
numPlayers2 | number of players in the alien team on round end
numPlayersRR | number of players in the ready room on round end
numPlayersSpec | number of spectators on round end
numPlayers  | total number of players on round end
maxPlayers | number of player slots on the server
numRookies1 | number of rookies in the marine team on round end
numRookies2 | number of rookies in the alien team on round end
numRookiesRR | number of rookies in the ready room on round end
numRookiesSpec | number of rookies spectating on round end
numRookies | total number of rookies on round end
skillTeam1 | accumulated skill points of the marine team
skillTeam2 | accumulated skill points of the alien team
averageSkill | average skill points of all players
killsTeam1 | number of kills (not deaths) for the marine team
killsTeam2 | number of kills (not deaths) for the alien team
kills | total number of kills
numRTs1 | number of extractors at round end
numRTs2 | number of harvesters at round end
numRTs | number of captured resource points at round end
numHives | number of hives at round end
numCCs | number of command stations at round end
numTechPointsCaptured | number of captured tech points at round end
biomassLevel | biomass level at round end

**data/ns2plus.sqlite3** contains all statistics gathered from the NS2+ mod. The table names and field names are largely identical to the ones NS2+ uses. The **roundId** field contains the unique round number that is referred to in all tables. The PlayerStats table contains accumulated data over all rounds, while the stats for individual rounds is stored in PlayerRoundStats.
For further information head to <br />
https://github.com/sclark39/NS2Plus

## Troubleshooting
If something does not work as expected, try **troubleshooting.php**. It tests for some common errors and will give advice on how to fix them.

## Feedback
Need something added? Head to <br />
http://forums.unknownworlds.com/discussion/139786/wonitor-server-side-statistics-tool <br />
New versions will be announced there as well.

## License Information
The project includes minified version of plotly.js, d3.js, husl.js and heatmap.js. The copyright lies with their respective owners.

https://github.com/husl-colors/husl <br />
http://www.husl-colors.org/ <br />
© 2015 Alexei Boronine

https://github.com/mbostock/d3 <br />
http://d3js.org/ <br />
© 2010-2016, Michael Bostock

https://github.com/plotly/plotly.js <br />
https://plot.ly/javascript/ <br />
© 2015 Plotly, Inc. <br />
Code released under the MIT license.

https://github.com/pa7/heatmap.js <br />
https://www.patrick-wied.at/static/heatmapjs/ <br />
© 2014, Patrick Wied


Everything else is free to use as you see fit.
