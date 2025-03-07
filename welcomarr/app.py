from flask import Flask, render_template, request, redirect, url_for, flash, session
import os
import uuid
import json
from datetime import datetime, timedelta

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'welcomarr-secret-key')

# Data storage - in a real app, this would be a database
DATA_FILE = 'data.json'

def load_data():
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, 'r') as f:
            return json.load(f)
    return {
        'admin': {
            'username': 'admin',
            'password': 'admin',  # In a real app, this would be hashed
        },
        'invitations': [],
        'users': []
    }

def save_data(data):
    with open(DATA_FILE, 'w') as f:
        json.dump(data, f, indent=4)

# Ensure data file exists
if not os.path.exists(DATA_FILE):
    save_data(load_data())

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        
        data = load_data()
        if username == data['admin']['username'] and password == data['admin']['password']:
            session['logged_in'] = True
            session['username'] = username
            flash('Login successful!', 'success')
            return redirect(url_for('admin_dashboard'))
        else:
            flash('Invalid credentials!', 'danger')
    
    return render_template('login.html')

@app.route('/logout')
def logout():
    session.clear()
    flash('You have been logged out!', 'info')
    return redirect(url_for('login'))

@app.route('/admin')
def admin_dashboard():
    if not session.get('logged_in'):
        flash('Please login first!', 'warning')
        return redirect(url_for('login'))
    
    data = load_data()
    return render_template('admin_dashboard.html', 
                          invitations=data['invitations'],
                          users=data['users'])

@app.route('/admin/create_invitation', methods=['GET', 'POST'])
def create_invitation():
    if not session.get('logged_in'):
        flash('Please login first!', 'warning')
        return redirect(url_for('login'))
    
    if request.method == 'POST':
        data = load_data()
        
        # Generate a unique invitation code
        code = str(uuid.uuid4())[:8]
        
        # Get form data
        name = request.form.get('name')
        expiry_days = int(request.form.get('expiry_days', 7))
        max_uses = int(request.form.get('max_uses', 1))
        
        # Create invitation
        invitation = {
            'id': str(uuid.uuid4()),
            'code': code,
            'name': name,
            'created_at': datetime.now().isoformat(),
            'expires_at': (datetime.now() + timedelta(days=expiry_days)).isoformat(),
            'max_uses': max_uses,
            'uses': 0,
            'active': True
        }
        
        data['invitations'].append(invitation)
        save_data(data)
        
        flash(f'Invitation created successfully! Code: {code}', 'success')
        return redirect(url_for('admin_dashboard'))
    
    return render_template('create_invitation.html')

@app.route('/admin/delete_invitation/<invitation_id>', methods=['POST'])
def delete_invitation(invitation_id):
    if not session.get('logged_in'):
        flash('Please login first!', 'warning')
        return redirect(url_for('login'))
    
    data = load_data()
    data['invitations'] = [inv for inv in data['invitations'] if inv['id'] != invitation_id]
    save_data(data)
    
    flash('Invitation deleted successfully!', 'success')
    return redirect(url_for('admin_dashboard'))

@app.route('/invite/<code>')
def invitation_page(code):
    data = load_data()
    
    # Find the invitation
    invitation = next((inv for inv in data['invitations'] if inv['code'] == code and inv['active']), None)
    
    if not invitation:
        flash('Invalid or expired invitation code!', 'danger')
        return redirect(url_for('index'))
    
    # Check if invitation has expired
    expires_at = datetime.fromisoformat(invitation['expires_at'])
    if datetime.now() > expires_at:
        flash('This invitation has expired!', 'danger')
        return redirect(url_for('index'))
    
    # Check if invitation has reached max uses
    if invitation['uses'] >= invitation['max_uses']:
        flash('This invitation has reached its maximum number of uses!', 'danger')
        return redirect(url_for('index'))
    
    return render_template('invitation.html', invitation=invitation)

@app.route('/onboard', methods=['POST'])
def onboard():
    code = request.form.get('code')
    email = request.form.get('email')
    username = request.form.get('username')
    
    data = load_data()
    
    # Find the invitation
    invitation = next((inv for inv in data['invitations'] if inv['code'] == code and inv['active']), None)
    
    if not invitation:
        flash('Invalid or expired invitation code!', 'danger')
        return redirect(url_for('index'))
    
    # Create new user
    user = {
        'id': str(uuid.uuid4()),
        'email': email,
        'username': username,
        'created_at': datetime.now().isoformat(),
        'invitation_id': invitation['id']
    }
    
    # Update invitation uses
    for inv in data['invitations']:
        if inv['id'] == invitation['id']:
            inv['uses'] += 1
            break
    
    data['users'].append(user)
    save_data(data)
    
    # In a real app, this would send an invitation to Plex
    # For now, we'll just simulate it
    
    flash('You have been successfully onboarded! Check your email for Plex invitation.', 'success')
    return redirect(url_for('success'))

@app.route('/success')
def success():
    return render_template('success.html')

@app.route('/admin/settings', methods=['GET', 'POST'])
def settings():
    if not session.get('logged_in'):
        flash('Please login first!', 'warning')
        return redirect(url_for('login'))
    
    data = load_data()
    
    if request.method == 'POST':
        # Update admin password
        new_password = request.form.get('new_password')
        confirm_password = request.form.get('confirm_password')
        
        if new_password and new_password == confirm_password:
            data['admin']['password'] = new_password  # In a real app, this would be hashed
            save_data(data)
            flash('Settings updated successfully!', 'success')
        else:
            flash('Passwords do not match!', 'danger')
    
    return render_template('settings.html')

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=int(os.environ.get('PORT', 54370)), debug=True)