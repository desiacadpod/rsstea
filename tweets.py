import json
import sys
import os
from datetime import datetime
from pathlib import Path
from tweetapi import TweetAPI

# Load API key from ~/.env

# for local run, uncomment when testing
# from dotenv import load_dotenv
# load_dotenv(Path.home() / ".env")

# API_KEY = os.getenv("TWEETAPI_KEY")
# if not API_KEY:
#     print("❌ Error: TWEETAPI_KEY not found in ~/.env")
#     sys.exit(1)

# github action run:
API_KEY = os.environ["TWEETAPI_KEY"]
if not API_KEY:
    print("❌ Error: TWEETAPI_KEY not found in environment variables")
    sys.exit(1)

client = TweetAPI(api_key=API_KEY)


def fetch_tweets(username: str, output_file: str = "tweets_raw.json") -> dict:
    """
    Fetch tweets from a user using tweetapi.com
    (excludes reposts/retweets, replies, and quoted posts)
    """
    try:
        print(f"Fetching tweets for @{username}...")
        
        # Get user info first
        user_data = client.user.get_by_username(username=username)
        user_id = user_data.get("data", {}).get("id")
        
        if not user_id:
            print(f"❌ Could not find user ID for @{username}")
            sys.exit(1)
        
        # Get tweets for user
        tweets_data = client.user.get_tweets(user_id=user_id)
        
        # Save full API dump for inspection
        dump_file = "tweets_api_dump.json"
        with open(dump_file, "w", encoding="utf-8") as f:
            json.dump(tweets_data, f, indent=2, ensure_ascii=False)
        print(f"📦 Saved full API response to {dump_file} for inspection")
        
        tweets = []
        filtered_out = []
        
        for tweet in tweets_data.get("data", []):
            text = tweet.get("text", "")
            tweet_id = tweet.get("id", "")
            tweet_type = tweet.get("type", "")
            
            # Skip reposts (type == "retweet")
            if tweet_type == "retweet":
                filtered_out.append({"reason": "retweet (type=retweet)", "id": tweet_id, "text": text[:80]})
                continue
            
            # Skip if it's a reply (replyTo field is not null)
            if tweet.get("replyTo") is not None:
                filtered_out.append({"reason": "reply (replyTo not null)", "id": tweet_id, "text": text[:80]})
                continue
            
            tweet_obj = {
                "text": text,
                "url": f"https://twitter.com/{username}/status/{tweet_id}",
                "timestamp": tweet.get("created_at", ""),
                "type": tweet_type
            }
            tweets.append(tweet_obj)
        
        output_data = {
            "tweets": tweets,
            "username": username,
            "extracted_at": datetime.now().isoformat(),
            "count": len(tweets)
        }
        
        with open(output_file, "w", encoding="utf-8") as f:
            json.dump(output_data, f, indent=2, ensure_ascii=False)
        
        print(f"✓ Fetched and saved {len(tweets)} original tweets to {output_file}")
        print(f"⚠️  Filtered out {len(filtered_out)} tweets (reposts/replies)")
        
        # Save filtered out list for review
        if filtered_out:
            with open("tweets_filtered_out.json", "w", encoding="utf-8") as f:
                json.dump(filtered_out, f, indent=2, ensure_ascii=False)
            print(f"📋 Details of filtered tweets saved to tweets_filtered_out.json")
        
        return output_data
        
    except Exception as e:
        print(f"❌ Error fetching tweets: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


def test_fetch_tweets(username: str = "desiacadpod", output_file: str = "tweets_raw.json") -> dict:
    """
    Test mode: Load tweets from tweets_api_dump.json for testing without API calls.
    Processes the dump the same way as fetch_tweets.
    """
    try:
        print(f"📋 Test mode: Loading tweets from tweets_api_dump.json...")
        
        # Load the API dump file
        with open("tweets_api_dump.json", "r", encoding="utf-8") as f:
            tweets_data = json.load(f)
        
        tweets = []
        filtered_out = []
        
        for tweet in tweets_data.get("data", []):
            text = tweet.get("text", "")
            tweet_id = tweet.get("id", "")
            tweet_type = tweet.get("type", "")
            
            # Skip reposts (type == "retweet")
            if tweet_type == "retweet":
                filtered_out.append({"reason": "retweet (type=retweet)", "id": tweet_id, "text": text[:80]})
                continue
            
            # Skip if it's a reply (replyTo field is not null)
            if tweet.get("replyTo") is not None:
                filtered_out.append({"reason": "reply (replyTo not null)", "id": tweet_id, "text": text[:80]})
                continue
            
            tweet_obj = {
                "text": text,
                "url": f"https://twitter.com/{username}/status/{tweet_id}",
                "timestamp": tweet.get("created_at", ""),
                "type": tweet_type
            }
            tweets.append(tweet_obj)
        
        output_data = {
            "tweets": tweets,
            "username": username,
            "extracted_at": datetime.now().isoformat(),
            "count": len(tweets)
        }
        
        with open(output_file, "w", encoding="utf-8") as f:
            json.dump(output_data, f, indent=2, ensure_ascii=False)
        
        print(f"✓ Test mode: Processed {len(tweets)} original tweets → {output_file}")
        print(f"⚠️  Filtered out {len(filtered_out)} tweets (reposts/replies)")
        
        # Save filtered out list for review
        if filtered_out:
            with open("tweets_filtered_out.json", "w", encoding="utf-8") as f:
                json.dump(filtered_out, f, indent=2, ensure_ascii=False)
            print(f"📋 Details of filtered tweets saved to tweets_filtered_out.json")
        
        return output_data
        
    except FileNotFoundError:
        print("❌ Error: tweets_api_dump.json not found. Run fetch_tweets first to generate it.")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Error in test mode: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


def load_tweets_from_file(input_file: str = "tweets_raw.json") -> dict:
    """
    Load tweets that were manually extracted via browser console.
    
    Expected format from browser:
    {
        "tweets": [
            {"text": "...", "url": "https://twitter.com/user/status/...", "timestamp": "..."},
            ...
        ]
    }
    """
    with open(input_file, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    print(f"Loaded {len(data.get('tweets', []))} tweets from {input_file}")
    return data


def filter_tweets(input_file: str = "tweets_raw.json", output_file: str = "tweets.json") -> dict:
    """
    Filter out replies and retweets from tweets and merge with existing tweets.json (avoid duplicates).
    Uses tweets_api_dump.json to validate existing tweets that don't have type field.
    """
    
    with open(input_file, "r", encoding="utf-8") as f:
        new_data = json.load(f)
    
    # Load existing tweets if file exists
    existing_tweets = []
    if os.path.exists(output_file):
        with open(output_file, "r", encoding="utf-8") as f:
            existing_data = json.load(f)
            existing_tweets = existing_data.get("data", [])
    
    # Build a type lookup from tweets_api_dump.json for validation
    api_type_map = {}
    if os.path.exists("tweets_api_dump.json"):
        try:
            with open("tweets_api_dump.json", "r", encoding="utf-8") as f:
                api_dump = json.load(f)
                for tweet in api_dump.get("data", []):
                    tweet_id = tweet.get("id", "")
                    if tweet_id:
                        api_type_map[tweet_id] = tweet.get("type", "")
        except:
            pass  # If API dump can't be loaded, continue without it
    
    # Get existing tweet URLs to avoid duplicates
    existing_urls = {tweet.get("url") for tweet in existing_tweets}
    
    # Filter new tweets (remove replies and retweets)
    filtered = []
    for tweet in new_data.get("tweets", []):
        text = tweet.get("text", "").strip()
        url = tweet.get("url", "")
        tweet_type = tweet.get("type", "")
        
        # Skip retweets (check type field)
        if tweet_type == "retweet":
            continue
        
        # Skip if it's a reply or already exists
        if not text.startswith("@") and url not in existing_urls:
            filtered.append(tweet)
    
    # Filter existing tweets to remove retweets
    # Extract tweet ID from URL to check against API dump
    cleaned_existing = []
    for t in existing_tweets:
        url = t.get("url", "")
        # Extract tweet ID from URL
        import re
        match = re.search(r'/status/(\d+)', url)
        tweet_id = match.group(1) if match else None
        
        # Skip if explicitly marked as retweet
        if t.get("type") == "retweet":
            continue
        
        # Skip if API dump says it's a retweet
        if tweet_id and api_type_map.get(tweet_id) == "retweet":
            continue
        
        cleaned_existing.append(t)
    
    # Combine: new tweets first, then existing
    merged_tweets = filtered + cleaned_existing
    
    output_data = {
        "data": merged_tweets,
        "total_count": len(merged_tweets),
        "new_tweets_added": len(filtered),
        "last_updated": datetime.now().isoformat()
    }
    
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output_data, f, indent=2, ensure_ascii=False)
    
    removed_count = len(existing_tweets) - len(cleaned_existing)
    print(f"✓ Added {len(filtered)} new tweets (total: {len(merged_tweets)} tweets in feed)")
    if removed_count > 0:
        print(f"  Removed {removed_count} retweets from existing data")
    return output_data


if __name__ == "__main__":
    command = sys.argv[1] if len(sys.argv) > 1 else "fetch"
    
    if command == "filter":
        filter_tweets()
    elif command == "test":
        # Test mode: use tweets_api_dump.json instead of fetching from API
        print("🧪 Running in TEST MODE (using tweets_api_dump.json)\n")
        test_fetch_tweets("desiacadpod")
        # Automatically filter after processing
        filter_tweets()
    else:
        # Default: Fetch tweets for desiacadpod
        fetch_tweets("desiacadpod")
        # Automatically filter after fetching
        filter_tweets()
