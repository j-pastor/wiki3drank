import requests
import asyncio
import aiohttp
import json
import sys

# CHECK ARGUMENTS PASSED FROM THE COMMAND LINE
if len(sys.argv)>=2:
    items=sys.argv[1].split(",")
if len(sys.argv)>=3:
    xtools_method=sys.argv[2]
else:
    xtools_method=""
if len(sys.argv)<2:
    print("Missing parametters..")
    quit()

# ASYNCHONOUS FUNCTIONS TO GET XTOOLS DATA
async def get_xtools(i,url, session):
    global results_items
    async with session.get(url=url) as response:
        resp = await response.read()
        if response.status==200:
            inf=json.loads(resp)
            if "/prose/" in url:
                results_items[i]["nwords"]+=inf["words"]
                results_items[i]["nrefs"]+=inf["references"]
                results_items[i]["nsections"]+=inf["sections"]
                results_items[i]["nurefs"]+=inf["unique_references"]
            if "/links/" in url:
                results_items[i]["nlext"]+=inf["links_ext_count"]
                results_items[i]["nlout"]+=inf["links_out_count"]
                results_items[i]["nlin"]+=inf["links_in_count"]
        else:
            pass

async def main_xtools(i,urls):
    async with aiohttp.ClientSession(connector=aiohttp.TCPConnector(limit=100)) as session:
        ret = await asyncio.gather(*[get_xtools(i,url, session) for url in urls])

# INITIALIZATION OF DICTIONARY TO STORE RETRIEVED DATA
results_items={}
for i in items:
    results_items[i]={"label_en":"","label_es":"","nwikis":0,"nprops":0,"nuprops":0,"ninprops":0,"nuinprops":0,"nidprops":0,"nwords":0,"nsections":0,
    "nrefs":0,"nurefs":0,"nwords_wm":0,"nlext":0,"nlout":0,"nlin":0,"sitelinks":[]}

# FORMAT ITEMS FROM COMMAND LINE FOR SPARQL QUERIES
values=" wd:".join(items)
values="wd:"+values

# GET DATA FROM WIKIPEDIA-STATS FILE
f = open('wikipedia-stats.json')
wikipedia_data = json.load(f)

# GET NIDPROPS AND NUIDPROPS
sparql_props='SELECT ?item ?itemLabel (COUNT(?pid) as ?nidprops) (COUNT(distinct ?pid) as ?nuidprops) WHERE {SELECT DISTINCT ?item ?pid ?o ?itemLabel WHERE {VALUES ?item {'+values+'} ?item ?pid ?o. ?prop_id wikibase:directClaim ?pid ; (wdt:P31/(wdt:P279*)) wd:Q19847637. SERVICE wikibase:label { bd:serviceParam wikibase:language "es". }}} GROUP BY ?item ?itemLabel'
url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
data = requests.get(url, params={'query': sparql_props, 'format': 'json'}).json()
for prop in data["results"]["bindings"]:
    results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nidprops"]=int(prop["nidprops"]["value"])
    results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nuidprops"]=int(prop["nuidprops"]["value"])
    
# GET NPROPS AND NUPROPS
sparql_props='SELECT DISTINCT ?item ?itemLabel (COUNT(?p) AS ?nst) (COUNT(DISTINCT ?p) AS ?nust) WHERE {VALUES ?item {'+values+'} ?item ?p ?o. ?prop wikibase:directClaim ?p.  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }} GROUP BY ?item ?itemLabel'
url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
data = requests.get(url, params={'query': sparql_props, 'format': 'json'}).json()
for prop in data["results"]["bindings"]:
    results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["label_en"]=prop["itemLabel"]["value"]
    results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nprops"]=int(prop["nst"]["value"])-results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nidprops"]
    results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nuprops"]=int(prop["nust"]["value"])-results_items[prop["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nuidprops"]

# GET NINPROPS AND NUINPROPS
for i in items:
    sparql_inprops='SELECT (COUNT(distinct ?subject) AS ?nuinprops) (COUNT(*) AS ?ninprops) WHERE {{SELECT ?subject WHERE {VALUES ?item {wd:'+i+'} ?subject ?p ?item. ?propiedad wikibase:directClaim ?p. } LIMIT 1000000}}'
    url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
    data = requests.get(url, params={'query': sparql_inprops, 'format': 'json'}).json()
    for inprop in data["results"]["bindings"]:
        results_items[i]["ninprops"]=inprop["ninprops"]["value"]
        results_items[i]["nuinprops"]=inprop["nuinprops"]["value"]

# GET SPANISH LABEL, NWIKIS AND WIKIPEDIA ARTICLES (SITELINKS)
sparql_sitelinks='SELECT DISTINCT ?item ?itemLabel ?article WHERE {VALUES ?item {'+values+'} ?item ^schema:about ?article . FILTER (CONTAINS(str(?article),".wikipe")) SERVICE wikibase:label { bd:serviceParam wikibase:language "es". }}'
url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
data = requests.get(url, params={'query': sparql_sitelinks, 'format': 'json'}).json()
for sitelink in data["results"]["bindings"]:
    results_items[sitelink["item"]["value"].replace("http://www.wikidata.org/entity/","")]["label_es"]=sitelink["itemLabel"]["value"]
    results_items[sitelink["item"]["value"].replace("http://www.wikidata.org/entity/","")]["nwikis"]+=1
    results_items[sitelink["item"]["value"].replace("http://www.wikidata.org/entity/","")]["sitelinks"].append(sitelink["article"]["value"])

# GET DATA FROM XTOOLS USING WIKIPEDIA ARTICLES
for i in results_items:
    n_words_wm=0
    for article in results_items[i]["sitelinks"]:
        query=article.split("/wiki/")
        pattern=query[0].replace("https://","").split(".")
        lang=pattern[0]
        n_words_wm+=wikipedia_data[lang]["mean"]
    results_items[i]["nwords_wm"]=n_words_wm

for i in results_items:
    
    url_xtools=[]
    sitelinks_wikipedias={}
    
    for article in results_items[i]["sitelinks"]:
        l=article.replace("https://","").replace("http://","").split(".wikipedia.org")[0]
        sitelinks_wikipedias[l]={"lang":l,"article":article,"wikipedia_total_articles":wikipedia_data[l]["total_articles"]}
    
    sorted_list = sorted(sitelinks_wikipedias.items(), key = lambda x: x[1]['wikipedia_total_articles'], reverse=True)    

    sitelinks_wikipedias_ordered={}
    for key, value in sorted_list:
        sitelinks_wikipedias_ordered[key] = value

    k=0
    url_xtools=[]
    for sitelink in sitelinks_wikipedias_ordered:
        query=sitelinks_wikipedias_ordered[sitelink]["article"].split("/wiki/")
        url_xtools.append("https://xtools.wmflabs.org/api/page/prose/"+query[0].replace("https://","")+"/"+query[1])
        url_xtools.append("https://xtools.wmflabs.org/api/page/links/"+query[0].replace("https://","")+"/"+query[1])
        k+=1
        if k==35:
            asyncio.run(main_xtools(i,url_xtools))
            k=0
            url_xtools=[]
        if xtools_method!="full": break
    if k>0: asyncio.run(main_xtools(i,url_xtools))

# DELETE URLs SITELINKS FROM RESULTS AND DUMP JSON OUTPUT
for i in results_items:
    del results_items[i]["sitelinks"]
print(json.dumps(results_items))
