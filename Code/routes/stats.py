# routes/stats.py
from flask import Blueprint, render_template
from models.application import Application

stats_bp = Blueprint("stats", __name__)

ORDER = ["Pending", "Interview", "Accepted", "Rejected", "No Answer"]

def _header_counts():
    """Counts used in base.html header (offers/interviews/total)."""
    counts = {k: 0 for k in ORDER}
    for r in Application.query.all():
        # assume r.status matches one of ORDER; fallback to 'Pending'
        key = r.status if r.status in counts else "Pending"
        counts[key] += 1
    total = sum(counts.values())
    return counts, total

@stats_bp.route("/")
def stats():
    # header numbers
    counts, total = _header_counts()

    # chart series (same ORDER as mobile app)
    labels = ORDER
    data = [counts[k] for k in labels]

    return render_template(
        "stats.html",
        labels=labels,
        data=data,
        total=total,   # needed by stats.html (and base.html)
        counts=counts, # needed by base.html
        title="Stats",
    )
