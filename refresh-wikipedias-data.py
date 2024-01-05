import requests
import xtools as xtools
from bs4 import BeautifulSoup
from math import log
import asyncio
import aiohttp
import json
import math
import os
import sys

# GET STATISTICS FROM WIKIPEDIAS
async def get_wikipedia_data(url, session):
    global wikipedia_data
    # try:
    async with session.get(url=url) as response:
        try:
            resp = await response.read()
            html_text=str(resp.decode("utf-8"))
            soup = BeautifulSoup(html_text,"html.parser")
            l=url.replace("https://","").replace("http://","").split(".wikipedia.org")[0]
            if l!="zh-classical":
                 wikipedia_data[l]["total_articles"]=int(soup.find("tr",class_="mw-statistics-articles").find("td",class_="mw-statistics-numbers").get_text().replace(",","").replace(".","").replace(" ","").replace("٬","").replace(" ","").replace("’",""))
                 wikipedia_data[l]["total_words"]=int(soup.find("tr",id="mw-cirrussearch-article-words").find("td",class_="mw-statistics-numbers").get_text().replace(",","").replace(".","").replace(" ","").replace("٬","").replace(" ","").replace("’",""))
                 wikipedia_data[l]["status"]="OK"
            else:
                zhtrad="〇一二三四五六七八九"
                a_text=soup.find("tr",class_="mw-statistics-articles").find("td",class_="mw-statistics-numbers").get_text()
                w_text=soup.find("tr",id="mw-cirrussearch-article-words").find("td",class_="mw-statistics-numbers").get_text()
                new_a_text=""
                new_w_text=""
                for c in a_text:
                    for i in range(10):
                        if c==zhtrad[i]:
                            new_a_text+=str(i)
                for c in w_text:
                    for i in range(10):
                        if c==zhtrad[i]:
                            new_w_text+=str(i)

                wikipedia_data[l]["total_articles"]=int(new_a_text)
                wikipedia_data[l]["total_words"]=int(new_w_text)
                wikipedia_data[l]["status"]="OK"  
            if wikipedia_data[l]["total_articles"]>0:
                wikipedia_data[l]["mean"]=wikipedia_data[l]["total_words"]/wikipedia_data[l]["total_articles"]
        except:
            quit()


async def main_wikipedia_data(urls):
    async with aiohttp.ClientSession() as session:
        ret = await asyncio.gather(*[get_wikipedia_data(url, session) for url in urls])

sparql_wikis='select ?sitename ?sitelink WHERE {?s wdt:P31 wd:Q10876391 ; wdt:P856 ?sitelink .BIND(REPLACE(REPLACE(STRBEFORE(STR(?sitelink),".wikipedia.org"),"https://",""),"http://","") as ?sitename)} order by ?sitename'
url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
data = requests.get(url, params={'query': sparql_wikis, 'format': 'json'}).json()

wikipedia_data={}
stats_urls=[]

for wiki in data["results"]["bindings"]:
    lang_wiki=wiki["sitename"]["value"]
    wikipedia_data[lang_wiki]={"lang":lang_wiki,"statistics_page":"https://"+lang_wiki+".wikipedia.org/wiki/Special:Statistics","total_articles":0,"total_words":0,"mean":0,"status":"error"}
    if lang_wiki not in ["ru-sib","tokipona","tlh","ii","mo"]:
        stats_urls.append(wiki["sitelink"]["value"]+"wiki/Special:Statistics")

asyncio.run(main_wikipedia_data(stats_urls))

with open('wikipedia-stats.json', 'w') as f:
    json.dump(wikipedia_data, f)

for wiki in wikipedia_data:
    print(wiki,wikipedia_data[wiki]["lang"],wikipedia_data[wiki]["total_articles"],wikipedia_data[wiki]["total_words"],wikipedia_data[wiki]["status"])


