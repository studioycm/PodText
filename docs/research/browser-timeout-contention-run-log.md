# Contention investigation — raw run log (appendix)

Companion to `browser-timeout-contention-investigation.md`. Every run the
investigation executed, verbatim from the per-batch summaries (2026-08-04,
HEAD `7f4cc76` for the measurement arms; arms E/F/G ran the fix verification
as described in the main doc). Columns: run number, surface key, epoch start,
duration seconds, pest exit code, result, 1-minute load average at launch
(arm E adds the per-run isolation token). `acq` =
`MediaPickerBrowserTest --filter="keeps.the.acquisition.workspace"`;
`owner` = `OwnerImageWorkspaceBrowserTest.php`.

Interferer cadences: purge loop = `rm` contents of
`storage/framework/testing/disks/public` every 1s (arm D: 69 purge events;
arm E: 1 — isolation had emptied the shared root); pest loops = one full
`vendor/bin/pest --compact <file>` iteration back-to-back (~1-2s each); churn =
40 file rewrites per 0.5s cycle in an untracked repo-root scratch dir;
cpu = 8 busy-loop workers + 256MB `dd` cycles to the session scratchpad
(C2: 3 workers, no IO).

## recon
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785854121	26	0	PASS	2.85
1	owner	1785854155	14	0	PASS	2.64
```

## baseline
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785854206	7	0	PASS	2.93
2	acq	1785854213	17	0	PASS	3.33
3	acq	1785854230	7	0	PASS	3.37
4	acq	1785854237	17	0	PASS	3.15
5	acq	1785854254	17	0	PASS	2.73
6	acq	1785854271	7	0	PASS	2.57
7	acq	1785854278	17	0	PASS	2.64
8	acq	1785854295	28	0	PASS	3.34
9	acq	1785854323	6	0	PASS	3.10
10	acq	1785854329	7	0	PASS	3.09
11	acq	1785854336	57	1	FAIL	3.25
12	acq	1785854393	7	0	PASS	3.56
1	owner	1785854515	14	0	PASS	3.02
2	owner	1785854529	15	0	PASS	3.01
3	owner	1785854544	15	0	PASS	3.18
4	owner	1785854559	15	0	PASS	3.93
5	owner	1785854574	14	0	PASS	4.28
6	owner	1785854588	14	0	PASS	4.01
7	owner	1785854602	15	0	PASS	3.80
8	owner	1785854617	14	0	PASS	3.92
9	owner	1785854631	14	0	PASS	3.84
10	owner	1785854645	14	0	PASS	4.27
11	owner	1785854659	14	0	PASS	4.75
12	owner	1785854673	14	0	PASS	4.01
```

## baseline2
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785854704	17	0	PASS	3.56
2	acq	1785854721	7	0	PASS	4.20
3	acq	1785854728	27	0	PASS	4.33
4	acq	1785854755	7	0	PASS	4.13
5	acq	1785854762	6	0	PASS	4.12
6	acq	1785854768	26	0	PASS	3.71
7	acq	1785854794	26	0	PASS	6.94
8	acq	1785854820	6	0	PASS	4.92
9	acq	1785854826	6	0	PASS	4.68
10	acq	1785854832	17	0	PASS	4.47
11	acq	1785854849	25	0	PASS	3.69
12	acq	1785854874	18	0	PASS	2.79
```

## armD
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785854970	83	1	FAIL	1.04
2	acq	1785855053	83	1	FAIL	1.03
3	acq	1785855136	82	1	FAIL	2.64
4	acq	1785855218	83	1	FAIL	1.63
5	acq	1785855301	83	1	FAIL	1.11
6	acq	1785855384	82	1	FAIL	1.34
7	acq	1785855466	83	1	FAIL	1.32
8	acq	1785855549	82	1	FAIL	0.94
9	acq	1785855631	83	1	FAIL	1.13
10	acq	1785855714	83	1	FAIL	2.47
1	owner	1785855812	173	1	FAIL	3.32
2	owner	1785855985	163	1	FAIL	2.03
3	owner	1785856148	173	1	FAIL	2.23
4	owner	1785856321	162	1	FAIL	2.36
5	owner	1785856483	163	1	FAIL	1.75
```

## armA
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785856731	82	1	FAIL	3.02
2	acq	1785856813	82	1	FAIL	2.24
3	acq	1785856895	82	1	FAIL	2.23
4	acq	1785856977	82	1	FAIL	3.00
5	acq	1785857059	82	1	FAIL	2.45
6	acq	1785857141	82	1	FAIL	2.70
7	acq	1785857223	83	1	FAIL	3.57
8	acq	1785857306	83	1	FAIL	4.02
9	acq	1785857389	83	1	FAIL	2.68
10	acq	1785857472	83	1	FAIL	2.65
```

## armA0
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785857592	8	0	PASS	3.30
2	acq	1785857600	7	0	PASS	3.28
3	acq	1785857607	18	0	PASS	3.32
4	acq	1785857625	17	0	PASS	2.95
5	acq	1785857642	18	0	PASS	3.35
6	acq	1785857660	18	0	PASS	3.35
7	acq	1785857678	17	0	PASS	3.79
8	acq	1785857695	18	0	PASS	3.18
9	acq	1785857713	18	0	PASS	3.14
10	acq	1785857731	7	0	PASS	2.88
```

## armB
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785857768	18	0	PASS	2.79
2	acq	1785857786	7	0	PASS	2.61
3	acq	1785857793	6	0	PASS	2.44
4	acq	1785857799	7	0	PASS	2.56
5	acq	1785857806	6	0	PASS	2.36
6	acq	1785857812	18	0	PASS	2.14
7	acq	1785857830	17	0	PASS	2.42
8	acq	1785857847	6	0	PASS	2.89
9	acq	1785857853	17	0	PASS	2.98
10	acq	1785857870	7	0	PASS	2.76
```

## armC
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785857940	55	1	FAIL	5.97
2	acq	1785857995	104	1	FAIL	27.50
3	acq	1785858099	92	1	FAIL	23.46
4	acq	1785858192	91	1	FAIL	43.62
5	acq	1785858284	93	1	FAIL	62.49
6	acq	1785858377	93	1	FAIL	29.89
7	acq	1785858470	92	1	FAIL	28.43
8	acq	1785858562	94	1	FAIL	24.06
9	acq	1785858656	92	1	FAIL	35.93
10	acq	1785858748	93	1	FAIL	65.19
1	owner	1785859459	99	1	FAIL	15.44
2	owner	1785859558	33	0	PASS	25.59
3	owner	1785859591	38	0	PASS	20.29
4	owner	1785859629	39	0	PASS	20.17
5	owner	1785859668	76	1	FAIL	18.73
```

## armC2
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785859024	7	0	PASS	4.76
2	acq	1785859031	17	0	PASS	4.86
3	acq	1785859048	17	0	PASS	4.89
4	acq	1785859065	7	0	PASS	4.85
5	acq	1785859072	7	0	PASS	4.86
6	acq	1785859079	6	0	PASS	4.95
7	acq	1785859085	17	0	PASS	5.03
8	acq	1785859102	26	0	PASS	5.11
9	acq	1785859128	59	1	FAIL	7.60
10	acq	1785859187	39	0	PASS	12.22
```

## armE
```
run	key	start_ts	dur_s	exit	result	load1	token
1	acq	1785859913	28	0	PASS	3.93	iso466701
2	acq	1785859941	17	0	PASS	3.85	iso466702
3	acq	1785859958	7	0	PASS	3.22	iso466703
4	acq	1785859965	17	0	PASS	3.03	iso466704
5	acq	1785859982	17	0	PASS	3.17	iso466705
6	acq	1785859999	26	0	PASS	2.63	iso466706
7	acq	1785860025	17	0	PASS	2.64	iso466707
8	acq	1785860042	17	0	PASS	2.37	iso466708
9	acq	1785860059	17	0	PASS	2.50	iso466709
10	acq	1785860076	6	0	PASS	2.07	iso4667010
1	owner	1785860113	14	0	PASS	1.41	iso477141
2	owner	1785860127	14	0	PASS	1.62	iso477142
3	owner	1785860141	14	0	PASS	1.72	iso477143
4	owner	1785860155	13	0	PASS	1.65	iso477144
5	owner	1785860168	14	0	PASS	2.08	iso477145
```

## armF
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785862161	18	0	PASS	2.50
2	acq	1785862179	17	0	PASS	2.52
3	acq	1785862196	7	0	PASS	2.10
4	acq	1785862203	6	0	PASS	2.34
5	acq	1785862209	6	0	PASS	2.31
6	acq	1785862215	6	0	PASS	2.60
1	owner	1785862230	13	0	PASS	2.96
2	owner	1785862243	14	0	PASS	2.68
3	owner	1785862257	14	0	PASS	2.59
```

## armG
```
run	key	start_ts	dur_s	exit	result	load1
1	acq	1785862281	17	0	PASS	3.06
2	acq	1785862298	6	0	PASS	2.83
3	acq	1785862304	6	0	PASS	2.92
4	acq	1785862310	61	0	PASS	2.85
5	acq	1785862371	17	0	PASS	3.78
6	acq	1785862388	17	0	PASS	3.40
```

