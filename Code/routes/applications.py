from flask import Blueprint, render_template, request, redirect, url_for
from models.application import db, Application
from datetime import date

applications_bp = Blueprint("applications", __name__)

@applications_bp.get("/")
def list_applications():
    filt = request.args.get("filter")
    q = Application.query
    if filt in ["Pending","Interview","Accepted","Rejected","No Answer"]:
        q = q.filter_by(status=filt)
    rows = q.order_by(Application.id.desc()).all()
    return render_template("applications.html", rows=rows, filt=filt, title="Applications")

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
            return render_template("new.html", error="Company and Position are required.", form=request.form)
        db.session.add(app)
        db.session.commit()
        return redirect(url_for("home.home"))
    return render_template("new.html", error=None, form={})

@applications_bp.post("/set_status/<int:app_id>")
def set_status(app_id):
    new_status = request.form.get("status","Pending")
    row = Application.query.get_or_404(app_id)
    row.status = new_status
    db.session.commit()
    return redirect(request.referrer or url_for("applications.list_applications"))