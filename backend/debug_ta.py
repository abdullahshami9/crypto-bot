import pandas as pd
import pandas_ta as ta
import numpy as np

# Create dummy data
df = pd.DataFrame({
    'close': np.random.random(100) * 100,
    'high': np.random.random(100) * 105,
    'low': np.random.random(100) * 95,
    'open': np.random.random(100) * 100,
    'volume': np.random.random(100) * 1000
})

# Run indicators
df.ta.bbands(length=20, std=2, append=True)
df.ta.atr(append=True)

print("Columns:", df.columns.tolist())
