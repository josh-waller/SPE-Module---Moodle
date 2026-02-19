# ai_api_server.py
from flask import Flask, request, jsonify
import json
import sys
import os
import time

# Add the current directory to Python path so we can import ai_module
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from ai_module import process_json_input

app = Flask(__name__)

@app.route('/analyze', methods=['POST'])
def analyze():
    try:
        # Get JSON data from Moodle
        data = request.get_json()
        
        if not data:
            return jsonify({
                'status': 'error',
                'error': 'No data provided',
                'timestamp': int(time.time())
            }), 400
        
        # Process the data using existing AI module
        result = process_json_input(json.dumps(data))
        
        # Return the result
        return jsonify(json.loads(result))
        
    except Exception as e:
        return jsonify({
            'status': 'error',
            'error': str(e),
            'timestamp': int(time.time())
        }), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'message': 'AI API Server is running'})

if __name__ == '__main__':
    print("Starting AI API Server...")
    print("Server will be available at: http://localhost:8000")
    print("Health check: http://localhost:8000/health")
    print("Analysis endpoint: http://localhost:8000/analyze")
    app.run(host='0.0.0.0', port=8000, debug=True)