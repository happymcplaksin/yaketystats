/*
	Get physical memory size -- modified from original by Bill Hassell,
	HP Atlanta.   According to Bill's notes, this program will work on
	all versions of HP-UX from 7.0 (possibly earlier) through 11.xx

	Turns out that larger-memory 64-bit systems will overflow the
	32-bit pstat structures, so define _PSTAT64 and use long-longs.
	This quite probably will break on pre-11.00 HP-UX, so beware!

	To compile: cc -O +DAportable -s -o RAMsize RAMsize.c
*/

static char *rcsid = "$Header: /usr/local/src/TSS/RCS/RAMsize.c,v 1.2 2002/07/12 13:05:48 root Exp $";

#include <stdio.h>
#define _PSTAT64
#include <sys/pstat.h>
main()
{
	struct pst_static buf;

	pstat_getstatic(&buf, sizeof(struct pst_static), 1, 0);
	printf("Physical RAM: %lld bytes = %lld Kbytes = %lld Mbytes\n",
		buf.physical_memory*buf.page_size,
		buf.physical_memory*buf.page_size / 1024LL,
		buf.physical_memory*buf.page_size / 1024LL / 1024LL);
}
