from flask import Blueprint, render_template
from models.application import Application

home_bp = Blueprint("home", __name__)

@home_bp.route("/")
def home():
    rows = Application.query.order_by(Application.id.desc()).all()

    order = ["Pending", "Interview", "Accepted", "Rejected", "No Answer"]
    counts = {k: 0 for k in order}
    for r in rows:
        counts[r.status] = counts.get(r.status, 0) + 1
    total = sum(counts.values())
    recent = rows[:5]

    return render_template("index.html", rows=rows, counts=counts, total=total, recent=recent, title="Interviewly")
