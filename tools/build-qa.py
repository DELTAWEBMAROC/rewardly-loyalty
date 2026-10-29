"""Build a deterministic runtime-only QA archive; never publishes a release."""
from pathlib import Path
import argparse,zipfile,hashlib,json
parser=argparse.ArgumentParser();parser.add_argument('--output',required=True);args=parser.parse_args();root=Path(__file__).resolve().parents[1];output=Path(args.output).resolve();assert not output.exists(),'Never overwrite an existing candidate'
files=[]
for name in ['nodevora-customer-rewards-for-woocommerce.php','readme.txt','uninstall.php','includes','assets','languages']:
 base=root/name;files.extend([base]if base.is_file()else[p for p in base.rglob('*')if p.is_file()and p.name!='.DS_Store'])
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for f in sorted(files):
  rel=f.relative_to(root);assert not any(p.startswith('.')or p in ['tests','docs','__MACOSX']for p in rel.parts)
  info=zipfile.ZipInfo('nodevora-customer-rewards-for-woocommerce/'+rel.as_posix(),(2026,10,29,0,0,0));info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,f.read_bytes())
with zipfile.ZipFile(output)as z:assert z.testzip()is None
print(json.dumps({'filename':output.name,'bytes':output.stat().st_size,'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'files':len(files)},indent=2))
