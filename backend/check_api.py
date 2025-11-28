import requests

try:
    response = requests.get('http://localhost/crypto_intelligence/public/api/trades.php')
    print(f"Status Code: {response.status_code}")
    print("Response Content:")
    print(response.text)
    try:
        print("JSON Parsed:")
        print(response.json())
    except Exception as e:
        print(f"JSON Parse Error: {e}")
except Exception as e:
    print(f"Request Error: {e}")
