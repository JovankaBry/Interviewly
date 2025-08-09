from flask import Blueprint, render_template, request, redirect, url_for
from models.application import db, Application
from datetime import date

applications_bp = Blueprint("applications", __name__)
ORDER = ["Pending","Interview","Accepted","Rejected","No Answer"]

def _header_counts():
    counts = {k: 0 for k in ORDER}
    for r in Application.query.all():
        counts[r.status] = counts.get(r.status, 0) + 1
    total = sum(counts.values())
    return counts, total

@applications_bp.get("/")
def list_applications():
    filt = request.args.get("filter")
    q = Application.query
    if filt in ORDER:
        q = q.filter_by(status=filt)
    rows = q.order_by(Application.id.desc()).all()
    counts, total = _header_counts()
    return render_template("applications.html", rows=rows, filt=filt, counts=counts, total=total, title="Applications")

@applications_bp.route("/new", methods=["GET","POST"])
def new():
    if request.method == "POST":
        app = Application(
            company=request.form.get("company","").strip(),
            position=request.form.get("position","").strip(),
            location=request.form.get("location","").strip(),
            status=request.form.get("status","Pending"),
            applied_date=request.form.get("applied_date") or date.today().isoformat(),
            notes=request.form.get("notes","").strip(),
        )
        if not app.company or not app.position:
            counts, total = _header_counts()
            return render_template("new.html", error="Company and Position are required.",
                                   form=request.form, counts=counts, total=total)
        db.session.add(app)
        db.session.commit()
        return redirect(url_for("home.home"))
    counts, total = _header_counts()
    return render_template("new.html", error=None, form={}, counts=counts, total=total)

@applications_bp.post("/set_status/<int:app_id>")
def set_status(app_id):
    new_status = request.form.get("status","Pending")
    row = Application.query.get_or_404(app_id)
    row.status = new_status
    db.session.commit()
    return redirect(request.referrer or url_for("applications.list_applications"))
