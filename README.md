# Wiki3DRank Calculation

Wiki3DRank Calculation is a small web application that processes data obtained from Wikidata and Wikipedia to calculate a ranking of Wikidata items. It is a hybrid application developed under PHP and Python.

Files
=====

The Wiki3DRank calculation requires the following files:

index.php: is a PHP script that must be opened with the web browser. It includes the user interface for selecting Wikidata items, executing the Python script that retrieves the data, and displaying the results.

wiki3drank.py: Python script that retrieves data from Wikidata Query Service and XTools necessary for Wiki3DRank calculation. This script is used by index.php.

refresh.php – PHP script that runs refresh-wikipedias-data.py and should be run from the web browser.

refresh-wikipedias-data.py: Python script that updates a JSON file with global statistics of all Wikipedias.

wikipedia-stats-data.json: JSON file with the latest global statistics of all Wikipedias. Once copied to the server it must have appropriate read-write permissions so that it can be updated by refresh-wikipedias-data.py.

styles.css: index.php CSS style sheet

PHP scripts do not have any special requirements, since Javascript libraries are used through their corresponding CDNs.

PHP scripts require the JSON module to be installed (common on almost all servers) Python scripts require Python3 and the following packages: requests, asyncio, aiohttp, json, sys and BeautifulSoup.



Installation
============
Simply upload the files to the server, give the wikipedia-stats-data.json file the appropriate writing permissions and open index.php from the browser. It is convenient to run the refresh.php script from time to time to update the JSON file with the global statistical data of all Wikipedias.



Using Wiki3DRank Calculator
===========================
Enter the "Q" identifiers in the text entry of the form. Multiple "Qs" separated by spaces can be added. Wait for the script to retrieve the data.

The script can execute two Wiki3DRank calculation modes: fast (4-8 seconds per item) or complete (between 10-25 seconds depending on the amount of information for each item). The quick mode considers the 35 Wikipedias with the highest number of articles. The full mode uses all Wikipedias to calculate Wiki3DRank.

It is possible to download the data in multiple formats and export the stacked bar chart with the results to PNG.

Python scripts can also be run from the command line.


Authors
=======
* Juan-Antonio Pastor-Sánchez (University of Murcia, Spain)
* Tomás Saorín (University of Murcia, Spain)

License
=======
Wiki3DRAnk Calculation is available under GNU General Public LIcense v3.0
