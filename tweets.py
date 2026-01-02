import requests
import json

url = "https://api.x.com/2/users/1678804918080258048/tweets?exclude=retweets&tweet.fields=referenced_tweets&max_results=5"

payload = {}
headers = {
  'Authorization': '',
  'Cookie': '__cf_bm=b2P8Bv3Ni6VFAb.ur5PxLYhDbzvSR_KZK.XccqSeMU4-1767371510.5304973-1.0.1.1-UQGWnvI9cC7cnZhOlsJSK4f1YSgQgn4PheKPi6jgigkIVKAZRJlEfhJ_qhZr8fDDFtS4iwVVLUmAyYmtMGCjp51pbGt09I_wZXBXTrLplXKs8Mlw.o7AadZI1Xfq.hr_; guest_id=v1%3A176219384279811583; guest_id_ads=v1%3A176219384279811583; guest_id_marketing=v1%3A176219384279811583; personalization_id="v1_f7RI5PVl51HWhs+dEh77TQ=="'
}

# response = requests.request("GET", url, headers=headers, data=payload)

# with open("tweets.json", "w", encoding="utf-8") as f:
#     json.dump(response.json(), f, indent=2)

with open("tweets.json", "r", encoding="utf-8") as f:
    data = json.load(f)

all_tweet_ids = [
    tweet["id"]
    for tweet in data.get("data", [])
]



filtered_tweets = []

for tweet in data.get("data", []):
    remove_tweet = False

    for ref in tweet.get("referenced_tweets", []):
        if ref.get("type") == "replied_to" and ref.get("id") in all_tweet_ids:
            remove_tweet = True
            break

    if not remove_tweet:
        filtered_tweets.append(tweet)


# print(filtered_tweets)


with open("filtered_tweets.json", "w", encoding="utf-8") as f:
    json.dump({"data": filtered_tweets}, f, indent=2)
